<?php

namespace App\Livewire;

use App\Enums\ReceiptMatchSource;
use App\Enums\TransactionType;
use App\Models\GroceryReceipt;
use App\Models\Transaction;
use App\Services\Receipts\ReceiptTransactionMatcher;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LinkReceiptTransactionTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $receiptId;

    private ?GroceryReceipt $receipt = null;

    private bool $resolvedSuggestion = false;

    private ?int $suggestedTransactionId = null;

    public function mount(int $receiptId): void
    {
        $this->receiptId = $receiptId;

        $this->authorize('update', $this->receipt());
    }

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn (): Builder => app(ReceiptTransactionMatcher::class)
                ->pickerQuery($this->receipt(), $this->getTableSearch())
                ->with(['account', 'category']))
            ->searchable()
            ->searchUsing(function (): void {
                // pickerQuery() already applied the search, so the suggestion
                // can drop out of first place in the same builder.
            })
            ->columns([
                TextColumn::make('suggested')
                    ->label('')
                    ->getStateUsing(fn (Transaction $record): ?string => $this->suggestedLabel($record))
                    ->badge()
                    ->color('info')
                    ->placeholder(''),
                TextColumn::make('date')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('account.description')
                    ->label('Account'),
                TextColumn::make('place')
                    ->description(fn (Transaction $record): ?string => $record->description)
                    ->wrap(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, Transaction $record): string => number_format($state / 100, 2).' '.$record->currency_code)
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success')
                    ->alignEnd(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (TransactionType $state): string => match ($state) {
                        TransactionType::Reserved => 'gray',
                        TransactionType::Income, TransactionType::IncomeCash => 'success',
                        default => 'primary',
                    }),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Uncategorized'),
            ])
            ->recordActions([
                Action::make('link')
                    ->label('Link')
                    ->icon(Heroicon::OutlinedLink)
                    ->action(function (Transaction $record): void {
                        $receipt = $this->receipt();

                        abort_unless(auth()->user()?->can('update', $receipt), 403);
                        abort_unless(
                            app(ReceiptTransactionMatcher::class)->linkableQuery($receipt)->whereKey($record->id)->exists(),
                            404,
                        );

                        $receipt->update([
                            'transaction_id' => $record->id,
                            'match_source' => ReceiptMatchSource::Manual,
                        ]);

                        Notification::make()->title('Receipt linked to transaction')->success()->send();

                        $this->dispatch('grocery-receipt-linked');
                    }),
            ])
            ->emptyStateHeading('No transactions found');
    }

    public function render(): View
    {
        return view('livewire.link-receipt-transaction-table');
    }

    private function receipt(): GroceryReceipt
    {
        return $this->receipt ??= GroceryReceipt::query()->with('account')->findOrFail($this->receiptId);
    }

    private function suggestedLabel(Transaction $record): ?string
    {
        if (filled($this->getTableSearch())) {
            return null;
        }

        return $record->id === $this->suggestedTransactionId() ? 'Suggested' : null;
    }

    private function suggestedTransactionId(): ?int
    {
        if ($this->resolvedSuggestion) {
            return $this->suggestedTransactionId;
        }

        $this->resolvedSuggestion = true;
        $this->suggestedTransactionId = app(ReceiptTransactionMatcher::class)
            ->potentialMatch($this->receipt())
            ?->id;

        return $this->suggestedTransactionId;
    }
}
