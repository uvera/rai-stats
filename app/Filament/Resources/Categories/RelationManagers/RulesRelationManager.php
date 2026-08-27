<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('example')
                    ->label('Example merchant (from real transactions)')
                    ->options(fn () => Transaction::query()->distinct()->orderBy('place')->pluck('place', 'place'))
                    ->searchable()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('pattern', $state))
                    ->helperText('Pick a real observed merchant string to fill the pattern below, then trim it to the generalizable substring.'),
                TextInput::make('pattern')
                    ->label('Pattern')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Matches any transaction whose place contains this text (case-insensitive).'),
                TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority rules are matched first when a place could match more than one rule.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pattern')
            ->columns([
                TextColumn::make('pattern')->searchable(),
                TextColumn::make('priority')->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
