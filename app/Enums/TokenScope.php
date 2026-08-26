<?php

namespace App\Enums;

enum TokenScope: string
{
    case Self = 'self';
    case Family = 'family';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Just me',
            self::Family => 'Whole family',
        };
    }

    /**
     * The Sanctum ability string stored in personal_access_tokens.abilities
     * for a token issued with this scope.
     */
    public function ability(): string
    {
        return "scope:{$this->value}";
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public static function fromAbilities(array $abilities): ?self
    {
        return collect(self::cases())->first(fn (self $scope) => in_array($scope->ability(), $abilities, true));
    }
}
