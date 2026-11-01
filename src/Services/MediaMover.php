<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;

/**
 * Moves media between folders. Which folder a medium is in is only stored in the database, the files stay where
 * they are: an upload attaches the medium to the folder (or, in a sub folder, to its parent folder) with the
 * folder's collection, so a move does the same for the target folder.
 *
 * Only folders whose media belong to folders take part. The media of a model record's folder belong to the record
 * (e.g. a post), moving them out would take them away from it.
 */
class MediaMover
{
    /**
     * A folder at the top level or a sub folder, not a folder of a model type, a collection or a record
     */
    public static function holdsFolderMedia(Folder $folder): bool
    {
        return in_array($folder->model_type, [null, Folder::class], true) && filled($folder->collection);
    }

    /**
     * Whether the user may add and remove media in the folder, like the header actions of the media list
     */
    public static function canManage(Folder $folder): bool
    {
        if (! filament('filament-media-manager')->allowUserAccess || empty($folder->user_id)) {
            return true;
        }

        $user = auth()->user();

        return $user && $folder->user_id === $user->getAuthIdentifier() && $folder->user_type === get_class($user);
    }

    public static function canMoveFrom(Folder $folder): bool
    {
        return static::holdsFolderMedia($folder) && static::canManage($folder);
    }

    /**
     * Folders the media of a folder can be moved to, by name
     *
     * @return Builder<Folder>
     */
    public static function targets(Folder $from): Builder
    {
        $query = FolderAccess::visibleTo(auth()->user(), Folder::query())
            ->whereKeyNot($from->getKey())
            ->where(fn (Builder $query) => $query->whereNull('model_type')->orWhere('model_type', Folder::class))
            ->whereNotNull('collection')
            ->where('collection', '!=', '')
            ->orderBy('name');

        if (filament('filament-media-manager')->allowUserAccess && ($user = auth()->user())) {
            $query->where(fn (Builder $query) => $query
                ->whereNull('user_id')
                ->orWhere(fn (Builder $query) => $query
                    ->where('user_id', $user->getAuthIdentifier())
                    ->where('user_type', get_class($user))));
        }

        return $query;
    }

    /**
     * Move the media to the folder; media that don't belong to a folder are left out.
     *
     * @param  iterable<Media>  $media
     * @return int the number of media moved
     */
    public static function move(iterable $media, Folder $to): int
    {
        if (! static::holdsFolderMedia($to)) {
            return 0;
        }

        // The same owner as an upload to the folder (CreateMediaAction)
        $owner = $to->model_type ? $to->model : $to;

        if (! $owner instanceof Model) {
            return 0;
        }

        $order = (int) Media::query()->withoutGlobalScopes()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection_name', $to->collection)
            ->max('order_column');

        $moved = 0;

        foreach ($media as $medium) {
            if ($medium->model_type !== (new Folder)->getMorphClass()) {
                continue;
            }

            $medium->forceFill([
                'model_type' => $owner->getMorphClass(),
                'model_id' => $owner->getKey(),
                'collection_name' => $to->collection,
                'order_column' => ++$order,
            ])->save();

            $moved++;
        }

        return $moved;
    }
}
