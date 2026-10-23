<?php

namespace App\Enums;

/**
 * Which background process a SyncRun records.
 */
enum SyncKind: string
{
    case GrocerySync = 'grocery_sync';
    case BankLogin = 'bank_login';
    case BankImport = 'bank_import';

    public function label(): string
    {
        return match ($this) {
            self::GrocerySync => 'Grocery sync',
            self::BankLogin => 'Bank login',
            self::BankImport => 'Bank import',
        };
    }
}
