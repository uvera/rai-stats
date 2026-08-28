<?php

namespace App\Filament\Resources\GroceryAccounts\Schemas;

use App\Enums\ReceiptProvider;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GroceryAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('provider')
                    ->label('Provider')
                    ->options(fn () => collect(ReceiptProvider::cases())
                        ->mapWithKeys(fn (ReceiptProvider $p) => [$p->value => $p->label()]))
                    ->default(ReceiptProvider::Maxi->value)
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('The provider cannot be changed once the account has receipts.'),
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->helperText('A name for this account, e.g. "Mike — Metro".'),
                TextInput::make('email')
                    ->label('Account email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Account password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('Stored encrypted so syncs can re-authenticate on their own. Leave blank when editing to keep the current password.'),
                Select::make('user_id')
                    ->label('Match receipts against')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('No one — don’t auto-link to transactions')
                    ->helperText('Whose bank transactions this account’s receipts are matched to.'),
            ]);
    }
}
