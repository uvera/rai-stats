<?php

namespace App\Filament\Resources\GroceryReceipts\Pages;

use App\Enums\ReceiptMatchSource;
use App\Filament\Resources\GroceryReceipts\GroceryReceiptResource;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use App\Services\Receipts\ReceiptTransactionMatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewGroceryReceipt extends ViewRecord
{
    protected static string $resource = GroceryReceiptResource::class;

    public function getTitle(): string
    {
        /** @var GroceryReceipt $record */
        $record = $this->record;

        return $record->store_name.' · '.$record->purchased_at->format('d.m.Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('linkTransaction')
                ->label('Link transaction')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (?GroceryReceipt $record) => $record !== null && auth()->user()?->can('update', $record))
                ->form([
                    Select::make('transaction_id')
                        ->label('Transaction')
                        ->required()
                        ->searchable()
                        ->options(fn (GroceryReceipt $record) => (new ReceiptTransactionMatcher)
                            ->candidatesFor($record)
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Transaction $t) => [
                                $t->id => $t->date->format('d.m.Y').' · '.$t->place.' · '.number_format($t->amount_cents / 100, 2).' '.$t->currency_code,
                            ])),
                ])
                ->fillForm(fn (GroceryReceipt $record) => ['transaction_id' => $record->transaction_id])
                ->action(function (GroceryReceipt $record, array $data): void {
                    $record->update([
                        'transaction_id' => $data['transaction_id'],
                        'match_source' => ReceiptMatchSource::Manual,
                    ]);

                    Notification::make()->title('Receipt linked to transaction')->success()->send();
                }),

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
