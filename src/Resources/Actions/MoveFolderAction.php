<?php

namespace TomatoPHP\FilamentMediaManager\Resources\Actions;

use Filament\Actions;
use Filament\Forms\Components\Select;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Services\FolderMover;

class MoveFolderAction
{
    public static function make(int $folder_id): Actions\Action
    {
        $folder = fn (): ?Folder => Folder::query()->find($folder_id);

        return Actions\Action::make('move_folder')
            ->hiddenLabel()
            ->tooltip(trans('filament-media-manager::messages.folders.actions.move.label'))
            ->label(trans('filament-media-manager::messages.folders.actions.move.label'))
            ->icon('heroicon-o-arrow-up-on-square-stack')
            ->color('gray')
            ->visible(fn (): bool => ($current = $folder()) && filled(FolderMover::targetIds($current)))
            ->schema([
                Select::make('target')
                    ->label(trans('filament-media-manager::messages.folders.actions.move.target'))
                    ->options(function () use ($folder): array {
                        $current = $folder();

                        // By id, so not merged with ... which would number the keys anew
                        $topLevel = FolderMover::canMoveToTopLevel($current)
                            ? [FolderMover::TOP_LEVEL => trans('filament-media-manager::messages.folders.actions.move.top_level')]
                            : [];

                        return $topLevel + FolderMover::targets($current)->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data) use ($folder): void {
                // The options are only offered, move() checks the target again
                FolderMover::moveAndNotify($folder(), (int) $data['target']);
            });
    }
}
