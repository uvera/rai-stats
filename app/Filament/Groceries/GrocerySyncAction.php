<?php

namespace App\Filament\Groceries;

use App\Jobs\SyncGroceryAccountJob;
use App\Models\GroceryAccount;
use App\Support\GrocerySyncSession;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The two grocery sync entry points, shared by the accounts table and the
 * account edit page:
 *
 *  - sync(): runs straight away. Shown whenever the account can authenticate
 *    on its own (live token, refresh token, or a stored password).
 *  - signInAndSync(): collects a one-off password, hands it to the job via a
 *    single-read cache key, then syncs. Shown only when the account has no
 *    way to authenticate unattended.
 */
class GrocerySyncAction
{
    public static function sync(): Action
    {
        return Action::make('sync')
            ->label('Sync receipts')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (?GroceryAccount $record) => $record?->canSyncUnattended() && auth()->user()?->can('update', $record))
            ->action(fn (GroceryAccount $record) => self::dispatch($record));
    }

    public static function signInAndSync(): Action
    {
        return Action::make('signInAndSync')
            ->label('Sign in & sync')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('primary')
            ->visible(fn (?GroceryAccount $record) => $record !== null && ! $record->canSyncUnattended() && auth()->user()?->can('update', $record))
            ->schema([
                TextInput::make('password')
                    ->label(fn (GroceryAccount $record) => $record->provider->label().' password')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(fn (GroceryAccount $record, array $data) => self::dispatch($record, $data['password'] ?? null));
    }

    private static function dispatch(GroceryAccount $record, ?string $password = null): void
    {
        $sessionId = GrocerySyncSession::start($record->id);

        if (filled($password)) {
            GrocerySyncSession::setPassword($sessionId, $password);
        }

        SyncGroceryAccountJob::dispatch($record->id, $sessionId);

        Notification::make()
            ->title('Sync started')
            ->body('Receipts are being pulled in the background. Refresh in a moment.')
            ->success()
            ->send();
    }
}
