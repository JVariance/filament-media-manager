<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The poster image of a video: shown before the video plays, and wherever the video is shown as a picture.
 *
 * The image is stored next to the video, in a posters folder of the video's directory, and its file name in the
 * custom property "poster". Each image gets a new name, so a changed poster is never served from a cache.
 */
class VideoPoster
{
    public const PROPERTY = 'poster';

    /**
     * Image types a poster may have, with the extension it is stored with
     */
    protected const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function isVideo(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'video/');
    }

    public static function has(Media $media): bool
    {
        return filled($media->getCustomProperty(static::PROPERTY));
    }

    public static function url(Media $media): ?string
    {
        if (! static::has($media)) {
            return null;
        }

        return Storage::disk($media->disk)->url(static::path($media, $media->getCustomProperty(static::PROPERTY)));
    }

    /**
     * Store an image as the poster, in place of the one before
     *
     * @return bool false when the contents are not a JPEG, PNG or WebP image
     */
    public static function store(Media $media, string $contents): bool
    {
        $extension = static::extension($contents);

        if (! $extension) {
            return false;
        }

        $file = 'poster-' . Str::lower((string) Str::ulid()) . '.' . $extension;

        Storage::disk($media->disk)->put(static::path($media, $file), $contents);

        static::remove($media, save: false);

        $media->setCustomProperty(static::PROPERTY, $file);
        $media->save();

        return true;
    }

    /**
     * Store an image sent by the browser as a data URL, e.g. a frame of the video drawn on a canvas
     */
    public static function storeDataUrl(Media $media, string $dataUrl): bool
    {
        $contents = static::decodeDataUrl($dataUrl);

        return $contents !== null && static::store($media, $contents);
    }

    /**
     * The image in a data URL, null when it is not a JPEG, PNG or WebP image
     */
    public static function decodeDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/[a-z]+;base64,(.+)$/s', $dataUrl, $matches)) {
            return null;
        }

        $contents = base64_decode($matches[1], strict: true);

        return ($contents !== false) && static::extension($contents) ? $contents : null;
    }

    public static function remove(Media $media, bool $save = true): void
    {
        if (! static::has($media)) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $disk->delete(static::path($media, $media->getCustomProperty(static::PROPERTY)));

        // Spatie removes the video's directory only once it is empty
        if (! $disk->allFiles(static::directory($media))) {
            $disk->deleteDirectory(static::directory($media));
        }

        $media->forgetCustomProperty(static::PROPERTY);

        if ($save) {
            $media->save();
        }
    }

    protected static function extension(string $contents): ?string
    {
        $size = @getimagesizefromstring($contents);

        return static::TYPES[$size['mime'] ?? ''] ?? null;
    }

    protected static function directory(Media $media): string
    {
        $directory = dirname($media->getPathRelativeToRoot());

        return ($directory === '.' ? '' : $directory . '/') . 'posters';
    }

    protected static function path(Media $media, string $file): string
    {
        return static::directory($media) . '/' . basename($file);
    }
}
