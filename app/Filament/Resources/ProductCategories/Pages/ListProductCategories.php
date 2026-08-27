<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recategorizeItems')
                ->label('Recategorize items now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('maxi:recategorize-items');

                    Notification::make()
                        ->title('Receipt items recategorized')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
