<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The focal point of an image or video, in the custom property "focal" as x and y in percent from the top left.
 * Where the medium is cropped (object-fit: cover), this point stays in view.
 */
class FocalPoint
{
    public const PROPERTY = 'focal';

    public static function supports(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'image/') || str_starts_with((string) $media->mime_type, 'video/');
    }

    /**
     * @return ?array{x: int, y: int}
     */
    public static function get(Media $media): ?array
    {
        $focal = $media->getCustomProperty(static::PROPERTY);

        return isset($focal['x'], $focal['y']) ? ['x' => (int) $focal['x'], 'y' => (int) $focal['y']] : null;
    }

    /**
     * @param  ?array{x: int | float, y: int | float}  $focal  null for the center
     */
    public static function set(Media $media, ?array $focal): void
    {
        if (! isset($focal['x'], $focal['y'])) {
            $media->forgetCustomProperty(static::PROPERTY);

            return;
        }

        $clamp = fn (int | float | string $value): int => (int) max(0, min(100, round((float) $value)));

        $media->setCustomProperty(static::PROPERTY, ['x' => $clamp($focal['x']), 'y' => $clamp($focal['y'])]);
    }

    /**
     * For the style attribute of an <img> or <video> with object-fit: cover; null without a focal point
     */
    public static function style(Media $media): ?string
    {
        $focal = static::get($media);

        return $focal ? "object-position: {$focal['x']}% {$focal['y']}%;" : null;
    }
}
