<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class MyStats extends AbstractStatsPage
{
    protected static ?string $navigationLabel = 'My Stats';

    protected static ?string $title = 'My Stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected function scopeUserId(): ?int
    {
        return auth()->id();
    }

    public function showLeaderboard(): bool
    {
        return false;
    }
}
