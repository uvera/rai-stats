<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class FamilyStats extends AbstractStatsPage
{
    protected static ?string $navigationLabel = 'Family Stats';

    protected static ?string $title = 'Family Stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected function scopeUserId(): ?int
    {
        return null;
    }

    public function showLeaderboard(): bool
    {
        return true;
    }
}
