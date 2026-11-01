<?php

namespace TomatoPHP\FilamentMediaManager\Resources\Actions;

use Filament\Actions\BulkAction;
use Livewire\Component;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Services\MediaDownload;

class DownloadMediaBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('download')
            ->label(trans('filament-media-manager::messages.media.actions.download.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (iterable $records, Component $livewire): void {
                // One file as itself, several as a zip file named after the folder
                $folder = Folder::query()->find(session('folder_id'));

                $livewire->redirect(MediaDownload::url($records, $folder?->name));
            })
            ->deselectRecordsAfterCompletion();
    }
}
