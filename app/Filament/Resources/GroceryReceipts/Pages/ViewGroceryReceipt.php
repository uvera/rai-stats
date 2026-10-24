<?php

namespace App\Filament\Resources\GroceryReceipts\Pages;

use App\Filament\Resources\GroceryReceipts\GroceryReceiptResource;
use App\Models\GroceryReceipt;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class ViewGroceryReceipt extends ViewRecord
{
    protected static string $resource = GroceryReceiptResource::class;

    public function getTitle(): string
    {
        /** @var GroceryReceipt $record */
        $record = $this->record;

        return $record->store_name.' · '.$record->purchased_at->format('d.m.Y');
    }

    #[On('grocery-receipt-linked')]
    public function refreshAfterLink(): void
    {
        $this->record->refresh();

        $this->unmountAction();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('linkTransaction')
                ->label('Link transaction')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (?GroceryReceipt $record) => $record !== null && auth()->user()?->can('update', $record))
                ->modalHeading('Link transaction')
                ->modalDescription('The suggested match stays first until you search.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn (GroceryReceipt $record) => view(
                    'filament.grocery-receipts.link-transaction',
                    ['receiptId' => $record->getKey()],
                )),

            Action::make('unlinkTransaction')
                ->label('Unlink')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (?GroceryReceipt $record) => $record?->transaction_id !== null && auth()->user()?->can('update', $record))
                ->action(function (GroceryReceipt $record): void {
                    $record->update(['transaction_id' => null, 'match_source' => null]);

                    Notification::make()->title('Receipt unlinked')->success()->send();
                }),
        ];
    }
}
