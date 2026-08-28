<?php

namespace App\Filament\Resources\ProductCategories\RelationManagers;

use App\Models\GroceryReceiptItem;
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
                    ->label('Example item (from real receipts)')
                    ->options(fn () => GroceryReceiptItem::query()->distinct()->orderBy('name')->limit(500)->pluck('name', 'name'))
                    ->searchable()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('pattern', $state))
                    ->helperText('Pick a real observed item name to fill the pattern, then trim it to the generalizable substring.'),
                TextInput::make('pattern')
                    ->label('Pattern')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Matches any receipt item whose name contains this text (case-insensitive).'),
                TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority rules match first.'),
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
