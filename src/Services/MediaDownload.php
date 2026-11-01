<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A download of one file, or of several as a zip file. The browser is sent to a signed panel route that streams it,
 * so that a large file or zip is not held in memory as a Livewire response would be. The signature is made when the
 * download is asked for, valid for a few minutes, and keeps the list of media from being changed.
 */
class MediaDownload
{
    /**
     * @param  iterable<Media>  $media
     * @param  ?string  $name  the name of the zip file, without ".zip"
     */
    public static function url(iterable $media, ?string $name = null): string
    {
        $ids = collect($media)->map(fn (Media $medium): int | string => $medium->getKey())->implode(',');

        return URL::temporarySignedRoute(
            filament()->getCurrentOrDefaultPanel()->generateRouteName('media-manager.media.download'),
            now()->addMinutes(10),
            ['media' => $ids, 'name' => Str::slug($name ?: 'media') ?: 'media'],
        );
    }
}
