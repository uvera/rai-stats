<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\TransactionType;
use App\Models\Account;
use Filament\Forms\Components\DatePicker;
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
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('account.description')
                    ->label('Account')
                    ->description(fn ($record) => $record->account?->number)
                    ->searchable(),
                TextColumn::make('place')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description)
                    ->toggleable(),
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
                    }),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
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
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ]);
    }
}
