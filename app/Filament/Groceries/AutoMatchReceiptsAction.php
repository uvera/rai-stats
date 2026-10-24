<?php

namespace App\Filament\Groceries;

use App\Models\GroceryAccount;
use App\Services\Receipts\ReceiptTransactionMatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Re-runs the strict receipt auto-match for receipts already in the database.
 * Sync only tries to link a receipt as it is imported.
 */
class AutoMatchReceiptsAction
{
    public static function forAll(): Action
    {
        return self::make('autoMatchReceipts')
            ->action(function (): void {
                self::run(null);
            });
    }

    public static function forAccount(): Action
    {
        return self::make('autoMatchReceipts')
            ->action(function (GroceryAccount $record): void {
                self::run($record);
            });
    }

    private static function make(string $name): Action
    {
        return Action::make($name)
            ->label('Auto-match receipts')
            ->icon(Heroicon::OutlinedBolt)
            ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
            ->requiresConfirmation()
            ->modalHeading('Auto-match receipts')
            ->modalDescription('Links each unlinked receipt only when its grocery account has a user and exactly one bank charge fits the amount, date, and store.');
    }

    private static function run(?GroceryAccount $account): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if ($account !== null && $account->user_id === null) {
            Notification::make()
                ->title('No receipts matched')
                ->body('Set Match receipts against on this account first.')
                ->warning()
                ->send();

            return;
        }

        $linked = app(ReceiptTransactionMatcher::class)->autoMatchUnlinked($account);

        if ($linked === 0) {
            Notification::make()
                ->title('No receipts matched')
                ->body('Auto-match links a receipt only when its grocery account has a user and exactly one bank charge fits.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($linked === 1 ? 'Linked 1 receipt' : "Linked {$linked} receipts")
            ->success()
            ->send();
    }
}
