<?php

namespace App\Support;

use App\Enums\SyncKind;
use App\Enums\SyncRunStatus;
use App\Filament\Resources\SyncRuns\SyncRunResource;
use App\Models\SyncRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Writes a Filament database notification for a finished sync report.
 * notifyNow so the bell row is stored in the same process as the job,
 * rather than queued behind it.
 */
class SyncRunNotifier
{
    public static function send(SyncRun $run): void
    {
        $user = $run->user;

        if ($user === null || $run->status === SyncRunStatus::Running) {
            return;
        }

        $notification = Notification::make()
            ->title(self::title($run))
            ->body($run->message ?: $run->summary)
            ->actions([
                Action::make('view')
                    ->label('View report')
                    ->url(SyncRunResource::getUrl('view', ['record' => $run])),
            ]);

        match ($run->status) {
            SyncRunStatus::Succeeded => $notification->success(),
            SyncRunStatus::Partial, SyncRunStatus::NeedsPassword => $notification->warning(),
            SyncRunStatus::Failed => $notification->danger(),
            SyncRunStatus::Running => null,
        };

        $user->notifyNow($notification->toDatabase());
    }

    private static function title(SyncRun $run): string
    {
        return match ($run->kind) {
            SyncKind::GrocerySync => match ($run->status) {
                SyncRunStatus::Succeeded => 'Grocery sync finished',
                SyncRunStatus::NeedsPassword => 'Grocery sync needs a password',
                default => 'Grocery sync failed',
            },
            SyncKind::PromotionRefresh => match ($run->status) {
                SyncRunStatus::Succeeded => 'Promotion refresh finished',
                SyncRunStatus::NeedsPassword => 'Promotion refresh needs a password',
                default => 'Promotion refresh failed',
            },
            SyncKind::BankImport => match ($run->status) {
                SyncRunStatus::Succeeded => 'Import finished',
                SyncRunStatus::Partial => 'Import finished with errors',
                default => 'Import failed',
            },
            SyncKind::BankLogin => 'Bank login failed',
        };
    }
}
