<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;

/**
 * Moves a folder into another folder or up to the top level. A sub folder points to its parent through
 * model_type = Folder and model_id, and its media belong to the parent (as an upload to a sub folder does), so they
 * move along to the new parent, or to the folder itself at the top level. The media of the folder's own sub folders
 * belong to the folder, they stay as they are.
 *
 * Only folders and sub folders whose media belong to folders take part, like for moving media (MediaMover).
 */
class FolderMover
{
    /**
     * The id that stands for the top level in the targets of a folder
     */
    public const TOP_LEVEL = 0;

    public static function canMove(Folder $folder): bool
    {
        if (! filament('filament-media-manager')->allowSubFolders) {
            return false;
        }

        if (! MediaMover::canMoveFrom($folder) || ! FolderResource::canEdit($folder)) {
            return false;
        }

        // Taking a sub folder out changes its parent, so the parent has to be the user's too
        $parent = static::parent($folder);

        return ! $parent || MediaMover::canManage($parent);
    }

    public static function canMoveToTopLevel(Folder $folder): bool
    {
        return $folder->model_type === Folder::class;
    }

    /**
     * Folders the folder can be moved into: like the targets of its media, but not the folder itself, one of its
     * sub folders, or the folder it is in already
     *
     * @return Builder<Folder>
     */
    public static function targets(Folder $folder): Builder
    {
        return MediaMover::targets($folder)
            ->whereKeyNot([
                ...static::descendantIds($folder),
                ...($folder->model_type === Folder::class ? [$folder->model_id] : []),
            ]);
    }

    /**
     * The ids of the targets, with TOP_LEVEL first when the folder can be moved up there
     *
     * @return list<int>
     */
    public static function targetIds(Folder $folder): array
    {
        if (! static::canMove($folder)) {
            return [];
        }

        return [
            ...(static::canMoveToTopLevel($folder) ? [static::TOP_LEVEL] : []),
            ...static::targets($folder)->pluck('id')->all(),
        ];
    }

    /**
     * Move the folder into the target, or up to the top level for TOP_LEVEL
     *
     * @return Folder | false | null the target, null for the top level, false when the folder can't be moved there
     */
    public static function move(Folder $folder, int $target): Folder | false | null
    {
        if (! in_array($target, static::targetIds($folder), true)) {
            return false;
        }

        $to = $target === static::TOP_LEVEL ? null : Folder::query()->find($target);

        DB::transaction(function () use ($folder, $to): void {
            $owner = $folder->model_type === Folder::class ? $folder->model_id : $folder->getKey();

            Media::query()->withoutGlobalScopes()
                ->where('model_type', (new Folder)->getMorphClass())
                ->where('model_id', $owner)
                ->where('collection_name', $folder->collection)
                ->update(['model_id' => $to?->getKey() ?? $folder->getKey()]);

            $folder->forceFill([
                'model_type' => $to ? Folder::class : null,
                'model_id' => $to?->getKey(),
            ])->save();
        });

        return $to;
    }

    /**
     * Move the folder and tell the user how it went, for the drag and drop on the pages
     */
    public static function moveAndNotify(Folder $folder, int $target): void
    {
        $to = static::move($folder, $target);

        if ($to === false) {
            return;
        }

        Notification::make()
            ->title($to
                ? trans('filament-media-manager::messages.folders.notifications.moved', ['folder' => $folder->name, 'target' => $to->name])
                : trans('filament-media-manager::messages.folders.notifications.moved_to_top_level', ['folder' => $folder->name]))
            ->success()
            ->send();
    }

    /**
     * All folders below the folder, also those the user can't see, so that none of them becomes its parent
     *
     * @return list<int>
     */
    protected static function descendantIds(Folder $folder): array
    {
        $ids = [$folder->getKey()];
        $level = [$folder->getKey()];

        while ($level) {
            $level = Folder::query()->withoutGlobalScopes()
                ->where('model_type', Folder::class)
                ->whereIn('model_id', $level)
                ->whereKeyNot($ids)
                ->pluck('id')
                ->all();

            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    protected static function parent(Folder $folder): ?Folder
    {
        return $folder->model_type === Folder::class
            ? Folder::query()->withoutGlobalScopes()->find($folder->model_id)
            : null;
    }
}
