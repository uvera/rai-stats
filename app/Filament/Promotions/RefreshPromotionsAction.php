<?php

namespace App\Filament\Promotions;

use App\Enums\ReceiptProvider;
use App\Jobs\RefreshMaxiPromotions;
use App\Jobs\RefreshMetroPromotions;
use App\Models\GroceryAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class RefreshPromotionsAction
{
    public static function make(): Action
    {
        return Action::make('refresh')
            ->label('Refresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(function (): bool {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return false;
                }

                return GroceryAccount::query()
                    ->whereIn('provider', [ReceiptProvider::Metro, ReceiptProvider::Maxi])
                    ->get()
                    ->contains(fn (GroceryAccount $account): bool => $account->canSyncUnattended() && $user->can('update', $account));
            })
            ->action(function (): void {
                $user = auth()->user();
                $actor = $user instanceof User ? $user : null;
                $count = RefreshMetroPromotions::dispatchUnattended($actor)
                    + RefreshMaxiPromotions::dispatchUnattended($actor);

                Notification::make()
                    ->title($count === 1 ? 'Refresh started for 1 account' : "Refresh started for {$count} accounts")
                    ->body('The result will show up under Sync reports.')
                    ->success()
                    ->send();
            });
    }
}
