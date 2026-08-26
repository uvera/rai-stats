<?php

namespace App\Filament\Pages;

use App\Models\Account;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

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

    protected function accountsScope(): Builder
    {
        return Account::query()->with('user');
    }

    protected function formatAccountOption(Account $account): string
    {
        return "{$account->user->name} - {$account->description} ({$account->number})";
    }
}
