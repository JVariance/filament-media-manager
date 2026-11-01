<?php

namespace TomatoPHP\FilamentMediaManager\Resources\FolderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Services\FolderMover;

class ListFolders extends ManageRecords
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        session()->forget('folder_id');
        session()->forget('folder_password');
    }

    /**
     * A folder at the top level dropped on another one
     */
    public function moveFolderToFolder(int $folder, int $target): void
    {
        $topLevelFolder = Folder::query()->whereNull('model_type')->find($folder);

        if ($topLevelFolder) {
            FolderMover::moveAndNotify($topLevelFolder, $target);
        }
    }
}
