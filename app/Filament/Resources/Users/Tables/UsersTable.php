<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')->searchable(),
                        TextColumn::make('email')->searchable()->color('gray'),
                    ]),
                    Stack::make([
                        TextColumn::make('raiffeisen_username')
                            ->label('Raiffeisen username')
                            ->placeholder('—'),
                        TextColumn::make('deleted_at')
                            ->label('Disabled at')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                        ->visibleFrom('md')
                        ->grow(false),
                    BadgeColumn::make('role')->grow(false),
                ]),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
