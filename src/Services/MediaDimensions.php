<?php

namespace TomatoPHP\FilamentMediaManager\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Stores the width and height of images and videos as the custom properties "width" and "height" (and the
 * "duration" of a video in seconds), so that a page can give <img> and <video> their size before they load and
 * nothing shifts.
 *
 * Images are measured with getimagesize(), SVGs by their width and height or their viewBox. Videos need ffprobe
 * (config filament-media-manager.dimensions.ffprobe); without it, they are left without dimensions.
 */
class MediaDimensions
{
    /**
     * @return bool whether dimensions were stored
     */
    public static function store(Media $media, bool $force = false): bool
    {
        if (! $force && $media->hasCustomProperty('width') && $media->hasCustomProperty('height')) {
            return false;
        }

        try {
            $dimensions = match (true) {
                str_starts_with((string) $media->mime_type, 'image/') => static::measureImage($media),
                str_starts_with((string) $media->mime_type, 'video/') => static::measureVideo($media),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        if (! $dimensions || ! $dimensions['width'] || ! $dimensions['height']) {
            return false;
        }

        foreach ($dimensions as $property => $value) {
            if ($value !== null) {
                $media->setCustomProperty($property, $value);
            }
        }

        $media->saveQuietly();

        return true;
    }

    /**
     * @return ?array{width: int, height: int}
     */
    protected static function measureImage(Media $media): ?array
    {
        // A local disk is read from the file, another one (e.g. S3) is loaded
        $path = $media->getPath();
        $contents = is_file($path) ? null : Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        if ($media->mime_type === 'image/svg+xml') {
            return static::measureSvg($contents ?? (string) file_get_contents($path));
        }

        $size = $contents === null ? @getimagesize($path) : @getimagesizefromstring((string) $contents);

        return $size ? ['width' => (int) $size[0], 'height' => (int) $size[1]] : null;
    }

    /**
     * The width and height attributes in pixels or without a unit, otherwise the viewBox
     *
     * @return ?array{width: int, height: int}
     */
    protected static function measureSvg(string $svg): ?array
    {
        $root = @simplexml_load_string($svg);

        if (! $root) {
            return null;
        }

        $width = (string) $root['width'];
        $height = (string) $root['height'];

        if (preg_match('/^[\d.]+(px)?$/', $width) && preg_match('/^[\d.]+(px)?$/', $height)) {
            return ['width' => (int) round((float) $width), 'height' => (int) round((float) $height)];
        }

        $viewBox = preg_split('/[\s,]+/', trim((string) $root['viewBox']));

        if (count($viewBox) === 4 && (float) $viewBox[2] > 0 && (float) $viewBox[3] > 0) {
            return ['width' => (int) round((float) $viewBox[2]), 'height' => (int) round((float) $viewBox[3])];
        }

        return null;
    }

    /**
     * The configured ffprobe; a bare name is also looked for where FFmpeg is usually installed, since a web server's
     * PHP often runs with an empty PATH
     */
    public static function ffprobe(): ?string
    {
        $ffprobe = config('filament-media-manager.dimensions.ffprobe', 'ffprobe');

        if (! $ffprobe || str_contains($ffprobe, DIRECTORY_SEPARATOR)) {
            return $ffprobe ?: null;
        }

        return (new ExecutableFinder)->find($ffprobe, null, ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']);
    }

    /**
     * The size the video is shown in: a video recorded upright on a phone is often stored sideways with a rotation
     *
     * @return ?array{width: int, height: int, duration: ?float}
     */
    protected static function measureVideo(Media $media): ?array
    {
        $ffprobe = static::ffprobe();

        if (! $ffprobe) {
            return null;
        }

        $path = $media->getPath();
        $source = is_file($path) ? $path : $media->getUrl();

        try {
            $result = Process::timeout(30)->run([
                $ffprobe,
                '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height:stream_tags=rotate:stream_side_data=rotation:format=duration',
                '-of', 'json',
                $source,
            ]);
        } catch (Throwable) {
            // ffprobe is optional; without it, videos have no dimensions
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        $info = json_decode($result->output(), true);
        $stream = $info['streams'][0] ?? null;

        if (! isset($stream['width'], $stream['height'])) {
            return null;
        }

        $rotation = (int) ($stream['tags']['rotate'] ?? collect($stream['side_data_list'] ?? [])->firstWhere('rotation')['rotation'] ?? 0);
        $sideways = abs($rotation) % 180 === 90;

        return [
            'width' => (int) ($sideways ? $stream['height'] : $stream['width']),
            'height' => (int) ($sideways ? $stream['width'] : $stream['height']),
            'duration' => isset($info['format']['duration']) ? round((float) $info['format']['duration'], 2) : null,
        ];
    }
}
