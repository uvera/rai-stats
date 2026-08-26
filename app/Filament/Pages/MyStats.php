<?php

namespace App\Filament\Pages;

use App\Models\Account;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MyStats extends AbstractStatsPage
{
    protected static ?string $navigationLabel = 'My Stats';

    protected static ?string $title = 'My Stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public function mount(): void
    {
        $this->initializeFilters();
    }

    protected function scopeUserId(): ?int
    {
        return auth()->id();
    }

    public function showLeaderboard(): bool
    {
        return false;
    }

    protected function accountsScope(): Builder
    {
        return Account::query()->where('user_id', auth()->id());
    }
}
