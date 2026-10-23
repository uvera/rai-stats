<?php

namespace App\Filament\Resources\SyncRuns;

use App\Enums\SyncKind;
use App\Filament\Resources\SyncRuns\Pages\ListSyncRuns;
use App\Filament\Resources\SyncRuns\Pages\ViewSyncRun;
use App\Filament\Resources\SyncRuns\Schemas\SyncRunInfolist;
use App\Filament\Resources\SyncRuns\Tables\SyncRunsTable;
use App\Models\SyncRun;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SyncRunResource extends Resource
{
    protected static ?string $model = SyncRun::class;

    protected static ?string $slug = 'sync-reports';

    protected static ?string $navigationLabel = 'Sync reports';

    protected static ?string $modelLabel = 'sync report';

    protected static ?string $pluralModelLabel = 'sync reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return SyncRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SyncRunsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);

            if ($user->isAdmin()) {
                $query->orWhereIn('kind', [SyncKind::GrocerySync, SyncKind::PromotionRefresh]);
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncRuns::route('/'),
            'view' => ViewSyncRun::route('/{record}'),
        ];
    }
}
