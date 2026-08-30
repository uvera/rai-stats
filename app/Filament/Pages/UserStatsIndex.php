<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class UserStatsIndex extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'User Stats';

    protected static ?string $title = 'User Stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected string $view = 'filament.pages.user-stats-index';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(User::query())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn (User $record) => UserStats::getUrl(['record' => $record])),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
            ]);
    }
}
