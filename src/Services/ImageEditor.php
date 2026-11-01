<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Events\MediaFileReplaced;

/**
 * Stores an image edited in the browser (cropped, rotated, flipped): in place of the image, or as a copy next to it.
 *
 * In place, the image keeps its file name, so every link and reference to it stays valid. Its original is kept in an
 * originals folder of its directory the first time it is edited, and can be restored. The dimensions, conversions
 * and responsive images follow the new image; the focal point is removed, since it was set on the old one.
 */
class ImageEditor
{
    /**
     * Types that can be edited, with the GD function that writes them
     */
    protected const TYPES = [
        'image/jpeg' => 'imagejpeg',
        'image/png' => 'imagepng',
        'image/webp' => 'imagewebp',
    ];

    /**
     * The file name of the kept original, so that it is found when the image's file is renamed
     */
    public const ORIGINAL_PROPERTY = 'original';

    public static function supports(Media $media): bool
    {
        return array_key_exists((string) $media->mime_type, static::TYPES);
    }

    public static function hasOriginal(Media $media): bool
    {
        return (bool) $media->getCustomProperty(static::ORIGINAL_PROPERTY);
    }

    /**
     * @return bool false when the contents are not an image
     */
    public static function replace(Media $media, string $contents): bool
    {
        $contents = static::inTypeOf($media, $contents);

        if ($contents === null) {
            return false;
        }

        $disk = Storage::disk($media->disk);

        if (! static::hasOriginal($media)) {
            $media->setCustomProperty(static::ORIGINAL_PROPERTY, $media->file_name);
            $disk->copy($media->getPathRelativeToRoot(), static::originalPath($media));
        }

        $disk->put($media->getPathRelativeToRoot(), $contents);

        static::updated($media, $contents);

        return true;
    }

    public static function restoreOriginal(Media $media): bool
    {
        if (! static::hasOriginal($media)) {
            return false;
        }

        $disk = Storage::disk($media->disk);
        $contents = (string) $disk->get(static::originalPath($media));

        $disk->put($media->getPathRelativeToRoot(), $contents);
        static::removeOriginal($media);

        static::updated($media, $contents);

        return true;
    }

    /**
     * Store the edited image as a new medium next to the image, with its texts
     */
    public static function saveAsCopy(Media $media, string $contents, string $name): ?Media
    {
        $contents = static::inTypeOf($media, $contents);
        $owner = $media->model;

        if (($contents === null) || ! $owner instanceof HasMedia) {
            return null;
        }

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);

        return $owner->addMediaFromString($contents)
            ->usingName($name)
            ->usingFileName(pathinfo($media->file_name, PATHINFO_FILENAME) . '-' . now()->format('YmdHis') . ($extension ? ".{$extension}" : ''))
            ->withCustomProperties(collect($media->custom_properties)->only(['title', 'description', 'alt'])->all())
            ->toMediaCollection($media->collection_name, $media->disk);
    }

    /**
     * Remove the kept original, e.g. when the image is deleted
     */
    public static function removeOriginal(Media $media): void
    {
        if (! static::hasOriginal($media)) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $disk->delete(static::originalPath($media));

        if (! $disk->allFiles(static::originalsDirectory($media))) {
            $disk->deleteDirectory(static::originalsDirectory($media));
        }

        $media->forgetCustomProperty(static::ORIGINAL_PROPERTY);
    }

    /**
     * The contents in the image's type: a browser may write another type than asked for (e.g. Safari writes PNG
     * when asked for WebP), and the image keeps its file name and type
     */
    protected static function inTypeOf(Media $media, string $contents): ?string
    {
        $type = @getimagesizefromstring($contents)['mime'] ?? null;

        if (! $type || ! isset(static::TYPES[$type]) || ! static::supports($media)) {
            return null;
        }

        if ($type === $media->mime_type) {
            return $contents;
        }

        $image = @imagecreatefromstring($contents);
        $write = static::TYPES[$media->mime_type];

        if (! $image || ! function_exists($write)) {
            return null;
        }

        if ($media->mime_type === 'image/jpeg') {
            // Without transparency, what was transparent turns black, so it gets a white background
            $background = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
            imagecopy($background, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $background;
        } else {
            imagesavealpha($image, true);
        }

        ob_start();
        $write($image, null, $media->mime_type === 'image/png' ? -1 : 92);

        return (string) ob_get_clean();
    }

    protected static function updated(Media $media, string $contents): void
    {
        $media->size = strlen($contents);

        $size = @getimagesizefromstring($contents);

        if ($size && $media->hasCustomProperty('width')) {
            $media->setCustomProperty('width', $size[0]);
            $media->setCustomProperty('height', $size[1]);
        }

        // The focal point was set on the old image
        $media->forgetCustomProperty(FocalPoint::PROPERTY);
        $media->save();

        app(FileManipulator::class)->createDerivedFiles($media, withResponsiveImages: filled($media->responsive_images));

        event(new MediaFileReplaced($media));
    }

    protected static function originalsDirectory(Media $media): string
    {
        $directory = dirname($media->getPathRelativeToRoot());

        return ($directory === '.' ? '' : $directory . '/') . 'originals';
    }

    protected static function originalPath(Media $media): string
    {
        $original = $media->getCustomProperty(static::ORIGINAL_PROPERTY);

        // Kept before its file name was stored, the original has the image's
        return static::originalsDirectory($media) . '/' . basename(is_string($original) ? $original : $media->file_name);
    }
}
