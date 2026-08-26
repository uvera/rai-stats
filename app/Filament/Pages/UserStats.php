<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class UserStats extends AbstractStatsPage
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'user-stats/{record}';

    public User $record;

    public function mount(User $record): void
    {
        $this->initializeFilters();

        $this->record = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->record->name} — Stats";
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function scopeUserId(): ?int
    {
        return $this->record->id;
    }

    public function showLeaderboard(): bool
    {
        return false;
    }

    protected function accountsScope(): Builder
    {
        return Account::query()->where('user_id', $this->record->id);
    }
}
