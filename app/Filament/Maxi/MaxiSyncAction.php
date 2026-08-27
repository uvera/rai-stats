<?php

namespace App\Filament\Maxi;

use App\Jobs\SyncMaxiAccountJob;
use App\Models\MaxiAccount;
use App\Support\MaxiSyncSession;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The two Moj Maxi sync entry points, shared by the accounts table and the
 * account edit page:
 *
 *  - sync(): reuses the stored ~1-year token, no prompt. Shown while the
 *    token is still valid.
 *  - signInAndSync(): collects the password (used once, never stored),
 *    hands it to the job via a one-shot cache key, then syncs. Shown when
 *    there is no valid token.
 */
class MaxiSyncAction
{
    public static function sync(): Action
    {
        return Action::make('sync')
            ->label('Sync receipts')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (?MaxiAccount $record) => $record?->tokenValid() && auth()->user()?->can('update', $record))
            ->action(fn (MaxiAccount $record) => self::dispatch($record));
    }

    public static function signInAndSync(): Action
    {
        return Action::make('signInAndSync')
            ->label('Sign in & sync')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('primary')
            ->visible(fn (?MaxiAccount $record) => $record !== null && ! $record->tokenValid() && auth()->user()?->can('update', $record))
            ->schema([
                TextInput::make('password')
                    ->label('Moj Maxi password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->helperText('Used once to sign in - never stored. Only the ~1-year token is kept.'),
            ])
            ->action(fn (MaxiAccount $record, array $data) => self::dispatch($record, $data['password'] ?? null));
    }

    private static function dispatch(MaxiAccount $record, ?string $password = null): void
    {
        $sessionId = MaxiSyncSession::start($record->id);

        if (filled($password)) {
            MaxiSyncSession::setPassword($sessionId, $password);
        }

        SyncMaxiAccountJob::dispatch($record->id, $sessionId);

        Notification::make()
            ->title('Sync started')
            ->body('Receipts are being pulled in the background. Refresh in a moment.')
            ->success()
            ->send();
    }
}
