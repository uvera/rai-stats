<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\CategorySource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('account.description')
                    ->label('Account')
                    ->description(fn ($record) => $record->account?->number)
                    ->searchable()
                    ->visibleFrom('lg'),
                TextColumn::make('place')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description)
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Uncategorized')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, $record) => number_format($state / 100, 2).' '.$record->currency_code)
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (TransactionType $state) => match ($state) {
                        TransactionType::Reserved => 'gray',
                        TransactionType::Income, TransactionType::IncomeCash => 'success',
                        default => 'primary',
                    })
                    ->visibleFrom('md'),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->visibleFrom('lg'),
            ])
            ->recordActions([
                Action::make('categorize')
                    ->label('Categorize')
                    ->icon(Heroicon::OutlinedTag)
                    ->color('gray')
                    ->form([
                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Uncategorized'),
                    ])
                    ->fillForm(fn ($record) => ['category_id' => $record->category_id])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'category_id' => $data['category_id'] ?? null,
                            'category_source' => $data['category_id'] ? CategorySource::Manual : null,
                        ]);

                        Notification::make()->title('Transaction categorized')->success()->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->label('Scope')
                    ->options([
                        'mine' => 'My transactions',
                        'all' => 'Everyone',
                    ])
                    ->default('mine')
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? 'mine') === 'mine') {
                            $query->where('user_id', auth()->id());
                        }

                        return $query;
                    }),
                SelectFilter::make('account_id')
                    ->label('Account')
                    ->options(fn () => Account::query()->pluck('description', 'id')),
                SelectFilter::make('type')
                    ->options(collect(TransactionType::cases())->mapWithKeys(fn (TransactionType $t) => [$t->value => str($t->name)->headline()])),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ]);
    }
}
