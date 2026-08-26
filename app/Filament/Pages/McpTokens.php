<?php

namespace App\Filament\Pages;

use App\Enums\TokenScope;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Self-service management of the personal access tokens used to
 * authenticate MCP tool calls (see routes/ai.php, App\Mcp\Servers\
 * RaiStatsServer). Every user manages only their own tokens - the same
 * "no admin gate" pattern as MyStats/FamilyStats, since every user already
 * has family-wide read access via FamilyStats.
 */
class McpTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'API Tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected string $view = 'filament.pages.mcp-tokens';

    /**
     * The plaintext of a just-created token - Sanctum only ever returns
     * this once, at creation time. Reset on every page load, never
     * persisted anywhere.
     */
    public ?string $plainTextToken = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PersonalAccessToken::query()
                ->where('tokenable_id', auth()->id())
                ->where('tokenable_type', User::class))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('abilities')
                    ->label('Scope')
                    ->formatStateUsing(function (mixed $state): string {
                        $abilities = is_array($state) ? $state : (json_decode((string) $state, true) ?? []);

                        return TokenScope::fromAbilities($abilities)?->label() ?? 'Unknown';
                    }),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PersonalAccessToken $record): void {
                        auth()->user()->tokens()->whereKey($record->id)->delete();

                        Notification::make()->title('Token revoked')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No API tokens yet')
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Create token')
                ->icon(Heroicon::OutlinedPlus)
                ->form([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Claude Desktop'),
                    Select::make('scope')
                        ->options(collect(TokenScope::cases())->mapWithKeys(fn (TokenScope $scope) => [$scope->value => $scope->label()]))
                        ->required()
                        ->default(TokenScope::Self->value)
                        ->helperText('"Whole family" lets this token see every user\'s accounts and transactions.'),
                ])
                ->action(function (array $data): void {
                    $scope = TokenScope::from($data['scope']);
                    $token = auth()->user()->createToken($data['name'], [$scope->ability()]);

                    $this->plainTextToken = $token->plainTextToken;

                    $this->resetTable();
                }),
        ];
    }

    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }
}
