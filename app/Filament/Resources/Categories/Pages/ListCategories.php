<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Support\MerchantCategoryData;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recategorize')
                ->label('Recategorize transactions now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('transactions:recategorize');

                    Notification::make()
                        ->title('Transactions recategorized')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
            Action::make('exportCategories')
                ->label('Export categories')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (MerchantCategoryData $merchantCategoryData) => response()->streamDownload(
                    fn () => print(json_encode($merchantCategoryData->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'merchant-categories.json',
                )),
            Action::make('importCategories')
                ->label('Import categories')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->form([
                    FileUpload::make('file')
                        ->label('JSON file')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['application/json', 'text/plain']),
                ])
                ->action(function (array $data, MerchantCategoryData $merchantCategoryData): void {
                    /** @var TemporaryUploadedFile $file */
                    $file = $data['file'];
                    $decoded = json_decode($file->get(), true);

                    if (! is_array($decoded)) {
                        Notification::make()->title('That file is not valid JSON')->danger()->send();

                        return;
                    }

                    $result = $merchantCategoryData->import($decoded);

                    Notification::make()
                        ->title('Categories imported')
                        ->body("Imported {$result['categories']} categories and {$result['rules']} rules. Run \"Recategorize transactions now\" to apply new rules.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
