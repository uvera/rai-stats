<?php

namespace App\Filament\Pages;

use App\Jobs\RaiffeisenLoginJob;
use App\Models\Account;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Services\Raiffeisen\TransactionImporter;
use App\Support\DateRange;
use App\Support\DateRangeMerger;
use App\Support\RaiffeisenImportSession;
use BackedEnum;
use DateTimeImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ImportTransactions extends Page
{
    protected string $view = 'filament.pages.import-transactions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import Transactions';

    public string $step = 'credentials';

    public ?string $username = null;

    public ?string $password = null;

    public ?string $importSessionId = null;

    /**
     * Plain arrays, not AccountBalance DTOs - Livewire can't serialize
     * arbitrary objects in public properties. productCoreId/currencyCodeNumeric
     * are read back from the persisted Account model in runImport() instead.
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

    /** @var array<int, array{account_number: string, from: string, to: string}> */
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
                    ->required(),
                DatePicker::make('toDate')
                    ->label('To')
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
     * pick ranges by hand. Reuses addRange() so existing coverage per half
     * is still respected and only the missing part gets queued.
     */
    public function queueGuidedImport(): void
    {
        $this->validate([
            'selectedAccountNumbers' => ['required', 'array', 'min:1'],
            'guidedYear' => ['required', 'integer'],
        ]);

        foreach ($this->selectedAccountNumbers as $accountNumber) {
            $this->selectedAccountNumber = $accountNumber;

            $this->fromDate = "{$this->guidedYear}-01-01";
            $this->toDate = "{$this->guidedYear}-06-30";
            $this->addRange();

            $this->fromDate = "{$this->guidedYear}-07-01";
            $this->toDate = "{$this->guidedYear}-12-31";
            $this->addRange();
        }
    }

    public function submitCredentials(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        auth()->user()->update(['raiffeisen_username' => $this->username]);

        $this->importSessionId = RaiffeisenImportSession::start(auth()->id());
        RaiffeisenImportSession::setPassword($this->importSessionId, $this->password);

        RaiffeisenLoginJob::dispatch($this->importSessionId, $this->username);

        // Never let the password linger in this component's state beyond
        // this request - it's already handed off to the job via its own
        // single-read cache key.
        $this->password = '';

        $this->step = 'waiting';
    }

    public function poll(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        $state = RaiffeisenImportSession::getState($this->importSessionId);

        if (! $state) {
            return;
        }

        if ($state['status'] === 'awaiting_push') {
            $this->waitingMessage = 'Approve the push notification on your phone...';
        }

        if ($state['status'] === 'ready') {
            // Plain arrays, not AccountBalance DTOs - see RaiffeisenLoginJob
            // for why (Laravel's cache stores refuse to unserialize
            // arbitrary objects by default).
            $fetchedAccounts = $state['accounts'];

            foreach ($fetchedAccounts as $account) {
                Account::firstOrCreate(
                    ['number' => $account['number']],
                    [
                        'user_id' => auth()->id(),
                        'description' => $account['description'],
                        'currency_code' => $account['currency_code'],
                        'currency_code_numeric' => $account['currency_code_numeric'],
                        'product_core_id' => $account['product_core_id'],
                    ]
                );
            }

            $this->accounts = array_map(fn (array $a) => [
                'number' => $a['number'],
                'description' => $a['description'],
                'currency_code' => $a['currency_code'],
            ], $fetchedAccounts);

            $this->selectedAccountNumber = $this->accounts[0]['number'] ?? null;
            $this->step = 'select';
        } elseif ($state['status'] === 'failed') {
            $this->errorMessage = $state['message'];
            $this->step = 'error';
        }
    }

    public function addRange(): void
    {
        $this->validate([
            'selectedAccountNumber' => ['required', 'string'],
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
        ]);

        $requested = new DateRange(new DateTimeImmutable($this->fromDate), new DateTimeImmutable($this->toDate));

        $account = Account::where('number', $this->selectedAccountNumber)->first();
        $existingCoverage = $account
            ? $account->importCoverages()->get()->map(fn ($c) => new DateRange($c->from_date, $c->to_date))->all()
            : [];

        // Also account for ranges already queued in this session but not
        // saved yet, so adding several ranges for the same account in one
        // sitting can't overlap each other either.
        $queuedForAccount = collect($this->queuedRanges)
            ->where('account_number', $this->selectedAccountNumber)
            ->map(fn ($r) => new DateRange(new DateTimeImmutable($r['from']), new DateTimeImmutable($r['to'])))
            ->all();

        $gaps = DateRangeMerger::subtract($requested, [...$existingCoverage, ...$queuedForAccount]);

        if (empty($gaps)) {
            $this->rangeNotice = 'That whole range is already covered - nothing new to add.';

            return;
        }

        $adjusted = count($gaps) !== 1
            || $gaps[0]->from != $requested->from
            || $gaps[0]->to != $requested->to;

        foreach ($gaps as $gap) {
            $this->queuedRanges[] = [
                'account_number' => $this->selectedAccountNumber,
                'from' => $gap->from->format('Y-m-d'),
                'to' => $gap->to->format('Y-m-d'),
            ];
        }

        $this->rangeNotice = $adjusted
            ? 'Part of that range is already imported - only the missing part was added.'
            : null;
    }

    /**
     * Queues the currently selected from/to range for every fetched
     * account, not just the one picked in the dropdown - accounts are
     * otherwise easy to leave with no coverage at all, since nothing queues
     * for them unless picked one at a time.
     */
    public function addRangeForAllAccounts(): void
    {
        foreach ($this->accounts as $account) {
            $this->selectedAccountNumber = $account['number'];
            $this->addRange();
        }
    }

    public function removeRange(int $index): void
    {
        unset($this->queuedRanges[$index]);
        $this->queuedRanges = array_values($this->queuedRanges);
    }

    public function runImport(): void
    {
        $state = RaiffeisenImportSession::getState($this->importSessionId);
        $cookies = $state['cookies'] ?? null;

        if (! $cookies) {
            $this->errorMessage = 'The login session expired - please start over.';
            $this->step = 'error';

            return;
        }

        $client = RaiffeisenClient::withCookies($cookies);
        $importer = new TransactionImporter;

        $byAccount = collect($this->queuedRanges)->groupBy('account_number');
        $results = [];

        foreach ($byAccount as $accountNumber => $ranges) {
            $account = Account::where('number', $accountNumber)->firstOrFail();

            $mergedRanges = DateRangeMerger::merge(
                $ranges->map(fn ($r) => new DateRange(new DateTimeImmutable($r['from']), new DateTimeImmutable($r['to'])))->all()
            );

            $inserted = 0;

            foreach ($mergedRanges as $range) {
                $transactions = $client->transactionalAccountTurnover(
                    $account->product_core_id,
                    $accountNumber,
                    $account->currency_code_numeric,
                    $range->from->format('d.m.Y'),
                    $range->to->format('d.m.Y'),
                );
                $reserved = $client->transactionalAccountReservedFunds($accountNumber);

                $inserted += $importer->importTurnover($account, auth()->id(), $transactions);
                $inserted += $importer->importReserved($account, auth()->id(), $reserved);

                $importer->recordCoverage($account, $range);
            }

            $results[] = [
                'account_number' => $accountNumber,
                'description' => $account->description,
                'inserted' => $inserted,
            ];
        }

        $this->importResults = $results;
        $this->queuedRanges = [];
        $this->step = 'done';

        Notification::make()
            ->title('Import complete')
            ->success()
            ->send();
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
