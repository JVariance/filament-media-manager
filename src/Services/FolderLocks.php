<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Support\Collection;
use TomatoPHP\FilamentMediaManager\Models\Folder;

/**
 * Which protected folders are open. A protected folder opens with its own password, and the folders below it are
 * locked as long as it is: they are only reached through it.
 *
 * The media pages remember the folders opened with their password in the session, one by one; the media picker
 * keeps its own list, for as long as it is open.
 */
class FolderLocks
{
    public const SESSION_KEY = 'filament-media-manager.unlocked_folders';

    public static function unlockInSession(Folder $folder): void
    {
        session()->put(static::SESSION_KEY, array_values(array_unique([...static::unlockedInSession(), (int) $folder->getKey()])));
    }

    /**
     * @return list<int>
     */
    public static function unlockedInSession(): array
    {
        return array_map('intval', (array) session()->get(static::SESSION_KEY, []));
    }

    /**
     * Whether the folder and all folders above it are unprotected or opened with their password
     *
     * @param  list<int> | null  $unlockedIds  the folders opened with their password, those of the session by default
     * @param  Collection<int, Folder> | null  $folders  all folders by id, to look up the folders above it
     */
    public static function isOpen(Folder $folder, ?array $unlockedIds = null, ?Collection $folders = null): bool
    {
        $unlockedIds ??= static::unlockedInSession();
        $folders ??= static::folders();
        $visited = [];

        while ($folder && ! in_array($folder->getKey(), $visited, true)) {
            if ($folder->is_protected && ! in_array((int) $folder->getKey(), $unlockedIds, true)) {
                return false;
            }

            $visited[] = $folder->getKey();
            $folder = static::parentOf($folder, $folders);
        }

        return true;
    }

    /**
     * The folder that the folder is shown in: the parent of a sub folder, the collection folder of a record folder,
     * the model type folder of a collection folder
     *
     * @param  Collection<int, Folder> | null  $folders  all folders by id
     */
    public static function parentOf(Folder $folder, ?Collection $folders = null): ?Folder
    {
        $folders ??= static::folders();

        if ($folder->model_type === Folder::class) {
            return $folders->get($folder->model_id);
        }

        if ($folder->model_type && $folder->model_id) {
            return $folders->first(fn (Folder $parent): bool => $parent->model_type === $folder->model_type
                && ! $parent->model_id
                && $parent->collection === $folder->collection);
        }

        if ($folder->model_type && $folder->collection) {
            return $folders->first(fn (Folder $parent): bool => $parent->model_type === $folder->model_type
                && ! $parent->model_id
                && ! $parent->collection);
        }

        return null;
    }

    /**
     * The names of the folder and the folders above it, e.g. "Floor plans › Water tower", so that folders of the
     * same name can be told apart in search results
     *
     * @param  Collection<int, Folder> | null  $folders  all folders by id
     */
    public static function path(?Folder $folder, ?Collection $folders = null): ?string
    {
        $folders ??= static::folders();
        $names = [];
        $visited = [];

        while ($folder && ! in_array($folder->getKey(), $visited, true)) {
            $visited[] = $folder->getKey();
            array_unshift($names, $folder->name);
            $folder = static::parentOf($folder, $folders);
        }

        return $names ? implode(' › ', $names) : null;
    }

    /**
     * All folders by id, without the user access global scope, like the folder levels show them
     *
     * @return Collection<int, Folder>
     */
    public static function folders(): Collection
    {
        return Folder::withoutGlobalScope('user')->get()->keyBy('id');
    }
}
