<?php

namespace App\Filament\Resources\MaxiAccounts\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaxiAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->helperText('A name for this account, e.g. "Dušan — Maxi".'),
                TextInput::make('email')
                    ->label('Moj Maxi email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('user_id')
                    ->label('Match receipts against')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('No one — don’t auto-link to transactions')
                    ->helperText('Whose bank transactions this account’s receipts are matched to.'),
            ]);
    }
}
