<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;

/**
 * Which media are in a folder (same logic as the folder scope of the media model), for queries over several
 * folders like a search
 */
class FolderMedia
{
    public static function scope(Builder $query, Folder $folder): void
    {
        if ($folder->model_type && $folder->model_id) {
            // Instance folder - show only media belonging to this specific model instance
            $query->where('model_type', $folder->model_type)
                ->where('model_id', $folder->model_id)
                ->where('collection_name', $folder->collection);
        } elseif ($folder->model_type && ! $folder->model_id) {
            // Collection folder - show media without model_id (not belonging to specific instances)
            $query->where('collection_name', $folder->collection)
                ->whereNull('model_id');
        } else {
            // Regular folder - show all media in collection
            $query->where('collection_name', $folder->collection);
        }
    }

    /**
     * The media of any of the folders; none without folders
     *
     * @param  iterable<Folder>  $folders
     */
    public static function scopeToAny(Builder $query, iterable $folders): void
    {
        $folders = collect($folders)->filter(fn (Folder $folder): bool => filled($folder->collection));

        if ($folders->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($folders): void {
            foreach ($folders as $folder) {
                $query->orWhere(fn (Builder $query) => static::scope($query, $folder));
            }
        });
    }

    /**
     * Whether a medium is in the folder, like scope() decides it
     */
    public static function holds(Folder $folder, Media $medium): bool
    {
        if ($folder->collection !== $medium->collection_name) {
            return false;
        }

        if ($folder->model_type && $folder->model_id) {
            return $medium->model_type === $folder->model_type && (string) $medium->model_id === (string) $folder->model_id;
        }

        return ! $folder->model_type || $medium->model_id === null;
    }

    /**
     * The folder of a medium among the folders
     *
     * @param  Collection<int, Folder>  $folders
     */
    public static function folderOf(Media $medium, Collection $folders): ?Folder
    {
        return $folders->first(fn (Folder $folder): bool => filled($folder->collection) && static::holds($folder, $medium));
    }
}
