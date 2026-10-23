<?php

namespace App\Filament\Resources\SyncRuns\Pages;

use App\Filament\Resources\SyncRuns\SyncRunResource;
use App\Models\SyncRun;
use Filament\Resources\Pages\ViewRecord;

class ViewSyncRun extends ViewRecord
{
    protected static string $resource = SyncRunResource::class;

    public function getTitle(): string
    {
        /** @var SyncRun $record */
        $record = $this->record;

        return $record->kind->label();
    }
}
