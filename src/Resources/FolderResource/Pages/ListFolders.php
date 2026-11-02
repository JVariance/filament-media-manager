<?php

namespace TomatoPHP\FilamentMediaManager\Resources\FolderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Services\FolderBreadcrumbs;
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

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        if (! request()->has('model_type')) {
            return parent::getBreadcrumbs();
        }

        $breadcrumbs = FolderBreadcrumbs::forList(request()->input('model_type'), request()->input('collection'));

        // The current level is the page itself, so it is not a link
        $current = array_pop($breadcrumbs);
        $breadcrumbs[] = $current;

        return $breadcrumbs;
    }

    public function mount(): void
    {
        parent::mount();

        // The folders opened with their password stay open for the session (FolderLocks)
        session()->forget('folder_id');

        // Back from the search results, the list shows the folders again instead of searching once more
        $this->tableSearch = '';
    }

    /**
     * The search looks in all folders and their media, on the media page, where their details and actions are
     */
    public function updatedTableSearch(): void
    {
        parent::updatedTableSearch();

        if (filled($this->tableSearch)) {
            $this->redirect(MediaResource::getUrl('index', ['search' => $this->tableSearch]), navigate: true);
        }
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
