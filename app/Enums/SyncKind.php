<?php

namespace App\Enums;

/**
 * Which background process a SyncRun records.
 */
enum SyncKind: string
{
    case GrocerySync = 'grocery_sync';
    case PromotionRefresh = 'promotion_refresh';
    case BankLogin = 'bank_login';
    case BankImport = 'bank_import';

    public function label(): string
    {
        return match ($this) {
            self::GrocerySync => 'Grocery sync',
            self::PromotionRefresh => 'Promotion refresh',
            self::BankLogin => 'Bank login',
            self::BankImport => 'Bank import',
        };
    }
}
