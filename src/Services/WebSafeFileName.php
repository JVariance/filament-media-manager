<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;

/**
 * File names that work in every URL: transliterated in the app's language (in German, "Ärger & Spaß.JPG" becomes
 * "aerger-und-spass.jpg"), lowercase, with hyphens for spaces and other characters. Only the file name changes;
 * the medium's name keeps its umlauts and capitals.
 *
 * Turned on with the plugin's webSafeFileNames(), for the uploads of the media manager; the command
 * filament-media-manager:web-safe-file-names renames the files of media added before.
 */
class WebSafeFileName
{
    /**
     * The word for "&" in the language the file name is transliterated in
     */
    protected const AND = [
        'ar' => 'wa',
        'cs' => 'a',
        'da' => 'og',
        'de' => 'und',
        'en' => 'and',
        'es' => 'y',
        'fi' => 'ja',
        'fr' => 'et',
        'it' => 'e',
        'nl' => 'en',
        'no' => 'og',
        'pl' => 'i',
        'pt' => 'e',
        'ru' => 'i',
        'sv' => 'och',
        'tr' => 've',
    ];

    public static function isEnabled(): bool
    {
        return (bool) static::plugin()?->webSafeFileNames;
    }

    /**
     * Name the file of an upload web-safe when the plugin says so, and unique among the owner's media in the collection
     */
    public static function apply(FileAdder $adder, Model $owner, string $collection): FileAdder
    {
        if (! static::isEnabled()) {
            return $adder;
        }

        return $adder->sanitizingFileName(fn (string $fileName): string => static::unique(static::make($fileName), $owner, $collection));
    }

    public static function make(string $fileName, ?string $language = null): string
    {
        $language ??= static::plugin()?->fileNameLanguage ?? app()->getLocale();

        $name = Str::slug(pathinfo($fileName, PATHINFO_FILENAME), '-', $language, [
            '@' => 'at',
            '&' => static::AND[$language] ?? static::AND[Str::before($language, '_')] ?? 'and',
            ...(static::plugin()?->fileNameDictionary ?? []),
        ]);

        $extension = preg_replace('/[^a-z0-9]/', '', Str::lower(pathinfo($fileName, PATHINFO_EXTENSION)));

        return ($name !== '' ? $name : 'file') . ($extension !== '' ? ".{$extension}" : '');
    }

    /**
     * The file name, or with a number added when another medium of the owner in the collection has it
     */
    public static function unique(string $fileName, Model $owner, string $collection, int | string | null $except = null): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $candidate = $fileName;

        for ($number = 2; static::isTaken($candidate, $owner, $collection, $except); $number++) {
            $candidate = "{$name}-{$number}" . ($extension !== '' ? ".{$extension}" : '');
        }

        return $candidate;
    }

    protected static function isTaken(string $fileName, Model $owner, string $collection, int | string | null $except): bool
    {
        return config('media-library.media_model')::query()
            ->withoutGlobalScopes()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection_name', $collection)
            ->where('file_name', $fileName)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->exists();
    }

    protected static function plugin(): ?FilamentMediaManagerPlugin
    {
        $panel = filament()->getCurrentOrDefaultPanel();

        if (! $panel?->hasPlugin('filament-media-manager')) {
            return null;
        }

        $plugin = $panel->getPlugin('filament-media-manager');

        return $plugin instanceof FilamentMediaManagerPlugin ? $plugin : null;
    }
}
