<?php

namespace App\Filament\Resources\MaxiReceipts\Pages;

use App\Enums\ReceiptMatchSource;
use App\Filament\Resources\MaxiReceipts\MaxiReceiptResource;
use App\Models\MaxiReceipt;
use App\Models\Transaction;
use App\Services\Maxi\ReceiptTransactionMatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMaxiReceipt extends ViewRecord
{
    protected static string $resource = MaxiReceiptResource::class;

    public function getTitle(): string
    {
        /** @var MaxiReceipt $record */
        $record = $this->record;

        return $record->store_name.' · '.$record->purchased_at->format('d.m.Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('linkTransaction')
                ->label('Link transaction')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (?MaxiReceipt $record) => $record !== null && auth()->user()?->can('update', $record))
                ->form([
                    Select::make('transaction_id')
                        ->label('Transaction')
                        ->required()
                        ->searchable()
                        ->options(fn (MaxiReceipt $record) => (new ReceiptTransactionMatcher)
                            ->candidatesFor($record)
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Transaction $t) => [
                                $t->id => $t->date->format('d.m.Y').' · '.$t->place.' · '.number_format($t->amount_cents / 100, 2).' '.$t->currency_code,
                            ])),
                ])
                ->fillForm(fn (MaxiReceipt $record) => ['transaction_id' => $record->transaction_id])
                ->action(function (MaxiReceipt $record, array $data): void {
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
                ->visible(fn (?MaxiReceipt $record) => $record?->transaction_id !== null && auth()->user()?->can('update', $record))
                ->action(function (MaxiReceipt $record): void {
                    $record->update(['transaction_id' => null, 'match_source' => null]);

                    Notification::make()->title('Receipt unlinked')->success()->send();
                }),
        ];
    }
}
