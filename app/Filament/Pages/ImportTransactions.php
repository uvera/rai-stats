<?php

namespace App\Filament\Pages;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Jobs\RaiffeisenImportJob;
use App\Jobs\RaiffeisenLoginJob;
use App\Models\Account;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\ImportRangeQueue;
use App\Support\RaiffeisenImportSession;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

class ImportTransactions extends Page
{
    protected string $view = 'filament.pages.import-transactions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import Transactions';

    public string $step = 'credentials';

    public ?string $username = null;

    public ?string $password = null;

    /**
     * Server-assigned and never a valid thing for the client to change:
     * whoever holds another user's session id could otherwise have that
     * user's bank cookies used on their behalf.
     */
    #[Locked]
    public ?string $importSessionId = null;

    /**
     * Plain arrays, not AccountBalance DTOs - Livewire can't serialize
     * arbitrary objects in public properties. productCoreId/currencyCodeNumeric
     * are read back from the persisted Account model in the import job instead.
     *
     * @var array<int, array{number: string, description: string, currency_code: string}>
     */
    public array $accounts = [];

    public ?string $selectedAccountNumber = null;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    /** @var array<int, string> */
    public array $selectedAccountNumbers = [];

    public ?int $guidedYear = null;

    /**
     * Built only through addRange()/queueRange(). Not #[Locked] (the tests
     * seed it directly), but the import job re-scopes every account to the
     * current user, so a tampered entry can't reach another user's data.
     *
     * @var array<int, array{account_number: string, from: string, to: string}>
     */
    public array $queuedRanges = [];

    public ?string $rangeNotice = null;

    public array $importResults = [];

    public ?string $errorMessage = null;

    public string $waitingMessage = 'Logging in...';

    public function mount(): void
    {
        $this->username = auth()->user()->raiffeisen_username;
        $this->toDate = now()->format('Y-m-d');
        $this->fromDate = now()->subMonth()->format('Y-m-d');
        $this->guidedYear = (int) now()->format('Y');
    }

    public function credentialsForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('username')
                ->label('Username')
                ->required(),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->required(),
        ]);
    }

    public function selectForm(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('selectedAccountNumber')
                    ->label('Account')
                    ->columnSpan(2)
                    ->options(fn () => collect($this->accounts)
                        ->mapWithKeys(fn (array $a) => [
                            $a['number'] => "{$a['description']} ({$a['currency_code']}, {$a['number']})",
                        ])
                        ->all())
                    ->required(),
                DatePicker::make('fromDate')
                    ->label('From')
                    ->native(false)
                    ->required(),
                DatePicker::make('toDate')
                    ->label('To')
                    ->native(false)
                    ->required()
                    ->afterOrEqual('fromDate'),
            ]);
    }

    public function guidedForm(Schema $schema): Schema
    {
        $currentYear = (int) now()->format('Y');

        return $schema
            ->columns(3)
            ->components([
                CheckboxList::make('selectedAccountNumbers')
                    ->label('Accounts')
                    ->columnSpan(2)
                    ->columns(2)
                    ->options(fn () => collect($this->accounts)
                        ->mapWithKeys(fn (array $a) => [
                            $a['number'] => "{$a['description']} ({$a['currency_code']}, {$a['number']})",
                        ])
                        ->all())
                    ->required(),
                Select::make('guidedYear')
                    ->label('Year')
                    ->options(collect(range($currentYear, $currentYear - 5))
                        ->mapWithKeys(fn (int $year) => [$year => (string) $year])
                        ->all())
                    ->required(),
            ]);
    }

    /**
     * Queues a Jan-Jun and a Jul-Dec range for the year, for every selected
     * account - the guided path to a full year's import without having to
     * pick ranges by hand. Goes through queueRange() so the two halves
     * can't overlap anything already queued this session.
     */
    public function queueGuidedImport(): void
    {
        $this->validate([
            'selectedAccountNumbers' => ['required', 'array', 'min:1'],
            'guidedYear' => ['required', 'integer'],
        ]);

        foreach ($this->selectedAccountNumbers as $accountNumber) {
            $this->queueRange($accountNumber, "{$this->guidedYear}-01-01", "{$this->guidedYear}-06-30");
            $this->queueRange($accountNumber, "{$this->guidedYear}-07-01", "{$this->guidedYear}-12-31");
        }

        $this->rangeNotice = null;
    }

    public function submitCredentials(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->update(['raiffeisen_username' => $this->username]);

        $run = SyncRun::open($user, SyncKind::BankLogin, summary: 'Signing in to Raiffeisen');

        $this->importSessionId = RaiffeisenImportSession::start($user->id);
        RaiffeisenImportSession::setPassword($this->importSessionId, $this->password);
        // Login incl. the mobile push wait is capped at ~3 minutes by the
        // client; give the poller a slightly longer deadline so a worker
        // that never picked the job up can't leave the wizard spinning.
        RaiffeisenImportSession::setState($this->importSessionId, [
            'poll_deadline' => now()->addSeconds(300)->timestamp,
            'sync_run_id' => $run->id,
        ]);

        RaiffeisenLoginJob::dispatch($this->importSessionId, $this->username, $run->id);

        // Never let the password linger in this component's state beyond
        // this request - it's already handed off to the job via its own
        // single-read cache key.
        $this->password = '';

        $this->step = 'waiting';
    }

    /**
     * Statuses the wizard actively polls through, i.e. a background job is
     * expected to move them along. If one is still set past its deadline the
     * worker has stalled.
     */
    private const POLLED_STATUSES = ['pending', 'awaiting_push', 'importing'];

    public function poll(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        $state = RaiffeisenImportSession::getState($this->importSessionId);

        // importSessionId is #[Locked], but belt-and-braces: only ever act
        // on a session this user started.
        if (! $state || ($state['user_id'] ?? null) !== auth()->id()) {
            return;
        }

        $status = $state['status'] ?? null;

        if (in_array($status, self::POLLED_STATUSES, true)
            && isset($state['poll_deadline'])
            && now()->timestamp > $state['poll_deadline']) {
            $message = 'This is taking longer than expected - the background worker may not be running. Please try again.';
            $this->errorMessage = $message;
            $this->step = 'error';

            // A stalled import keeps its running report. A login that never
            // started is the failure the wizard already explains.
            if ($status !== 'importing' && isset($state['sync_run_id'])) {
                SyncRun::query()->find($state['sync_run_id'])?->finish(
                    SyncRunStatus::Failed,
                    'Login failed',
                    $message,
                );
            }

            return;
        }

        match ($status) {
            'awaiting_push' => $this->waitingMessage = 'Approve the push notification on your phone...',
            'ready' => $this->handleAccountsReady($state['accounts']),
            'importing' => $this->enterImportingStep($state['import_results'] ?? []),
            'done' => $this->handleImportComplete($state['import_results'] ?? []),
            'failed' => $this->handleFailure($state['message'] ?? 'Something went wrong. Please try again.'),
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $fetchedAccounts
     */
    private function handleAccountsReady(array $fetchedAccounts): void
    {
        foreach ($fetchedAccounts as $account) {
            Account::syncFromBank($account, auth()->id());
        }

        $this->accounts = array_map(fn (array $a) => [
            'number' => $a['number'],
            'description' => $a['description'],
            'currency_code' => $a['currency_code'],
        ], $fetchedAccounts);

        $this->selectedAccountNumber = $this->accounts[0]['number'] ?? null;
        $this->step = 'select';
    }

    /**
     * @param  array<int, array{account_number: string, description: string, inserted: int, failed: bool, message?: ?string}>  $results
     */
    private function handleImportComplete(array $results): void
    {
        $this->importResults = $results;
        $this->step = 'done';
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function enterImportingStep(array $results): void
    {
        $this->importResults = $results;
        $this->waitingMessage = 'Importing your transactions...';
        $this->step = 'importing';
    }

    private function handleFailure(string $message): void
    {
        $this->errorMessage = $message;
        $this->step = 'error';
    }

    public function addRange(): void
    {
        $this->validate([
            'selectedAccountNumber' => ['required', 'string'],
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
        ]);

        $this->rangeNotice = match ($this->queueRange($this->selectedAccountNumber, $this->fromDate, $this->toDate)) {
            'duplicate' => 'That whole range is already queued - nothing new to add.',
            'trimmed' => 'Part of that range is already queued - only the missing part was added.',
            default => null,
        };
    }

    /**
     * Queues the currently selected from/to range for every fetched
     * account, not just the one picked in the dropdown - accounts are
     * otherwise easy to leave with no coverage at all, since nothing queues
     * for them unless picked one at a time.
     */
    public function addRangeForAllAccounts(): void
    {
        $this->validate([
            'selectedAccountNumber' => ['required', 'string'],
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
        ]);

        foreach ($this->accounts as $account) {
            $this->queueRange($account['number'], $this->fromDate, $this->toDate);
        }

        $this->rangeNotice = null;
    }

    /**
     * @return 'added'|'trimmed'|'duplicate'
     */
    private function queueRange(string $accountNumber, string $from, string $to): string
    {
        $added = ImportRangeQueue::add($this->queuedRanges, $accountNumber, $from, $to);
        $this->queuedRanges = $added['ranges'];

        return $added['status'];
    }

    public function removeRange(int $index): void
    {
        unset($this->queuedRanges[$index]);
        $this->queuedRanges = array_values($this->queuedRanges);
    }

    /**
     * Hands the queued ranges to RaiffeisenImportJob and switches to the
     * polling 'importing' step. The fetch is dozens of sequential bank
     * round-trips for a multi-account year - far too long to run inside
     * this Livewire request, exactly like the login step.
     */
    public function runImport(): void
    {
        $state = RaiffeisenImportSession::getState($this->importSessionId);

        if (empty($state['cookies'] ?? null)) {
            $this->errorMessage = 'The login session expired - please start over.';
            $this->step = 'error';

            return;
        }

        if ($this->queuedRanges === []) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $run = SyncRun::open($user, SyncKind::BankImport, summary: 'Importing transactions');

        RaiffeisenImportSession::setState($this->importSessionId, [
            'status' => 'importing',
            'import_results' => [],
            'poll_deadline' => now()->addSeconds(900)->timestamp,
            'sync_run_id' => $run->id,
        ]);

        RaiffeisenImportJob::dispatch($this->importSessionId, $user->id, $this->queuedRanges, $run->id);

        $this->importResults = [];
        $this->queuedRanges = [];
        $this->waitingMessage = 'Importing your transactions...';
        $this->step = 'importing';
    }

    /**
     * Back to picking ranges for another import, reusing the still-logged-in
     * session's cookies instead of forcing the user through RaiOnline login
     * (and, if enabled, the mobile push 2FA wait) again.
     */
    public function continueImporting(): void
    {
        $this->reset(['queuedRanges', 'rangeNotice', 'importResults']);
        $this->step = 'select';

        // Re-put the state to refresh its TTL, so a longer guided session
        // doesn't expire the cached cookies between imports.
        $state = RaiffeisenImportSession::getState($this->importSessionId);
        if ($state) {
            RaiffeisenImportSession::setState($this->importSessionId, $state);
        }
    }

    public function startOver(): void
    {
        if ($this->importSessionId) {
            RaiffeisenImportSession::clear($this->importSessionId);
        }

        $this->reset([
            'step', 'password', 'importSessionId', 'accounts', 'selectedAccountNumber',
            'selectedAccountNumbers', 'queuedRanges', 'rangeNotice', 'importResults',
            'errorMessage', 'waitingMessage',
        ]);
        $this->mount();
    }
}
