<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Services\MediaDimensions;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Storage::fake('public');

    $this->folder = Folder::factory()->create();
});

function addTo(Folder $folder, UploadedFile $file): Media
{
    return $folder->addMedia($file)->toMediaCollection($folder->collection)->refresh();
}

function svgFile(string $attributes): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" ' . $attributes . '></svg>');
}

function ffprobeOutput(int $width, int $height, ?int $rotation = null, float $duration = 4.5): string
{
    $stream = ['width' => $width, 'height' => $height];

    if ($rotation !== null) {
        $stream['side_data_list'] = [['side_data_type' => 'Display Matrix', 'rotation' => $rotation]];
    }

    return json_encode(['streams' => [$stream], 'format' => ['duration' => (string) $duration]]);
}

it('stores the width and height of an uploaded image', function () {
    $media = addTo($this->folder, UploadedFile::fake()->image('photo.jpg', 120, 80));

    expect($media->getCustomProperty('width'))->toBe(120)
        ->and($media->getCustomProperty('height'))->toBe(80);
});

it('measures an SVG by its size or its viewBox', function () {
    expect(addTo($this->folder, svgFile('width="64px" height="32"'))->custom_properties)
        ->toMatchArray(['width' => 64, 'height' => 32])
        ->and(addTo($this->folder, svgFile('width="100%" viewBox="0 0 300 150"'))->custom_properties)
        ->toMatchArray(['width' => 300, 'height' => 150]);
});

it('stores the size and duration of a video, upright when it was recorded sideways', function () {
    Process::fake([
        '*landscape*' => Process::result(ffprobeOutput(1920, 1080)),
        '*portrait*' => Process::result(ffprobeOutput(1920, 1080, rotation: -90, duration: 12.345)),
    ]);

    $landscape = addTo($this->folder, UploadedFile::fake()->create('landscape.mp4', 10, 'video/mp4'));
    $landscape->forceFill(['mime_type' => 'video/mp4'])->saveQuietly();
    $portrait = addTo($this->folder, UploadedFile::fake()->create('portrait.mp4', 10, 'video/mp4'));
    $portrait->forceFill(['mime_type' => 'video/mp4'])->saveQuietly();

    MediaDimensions::store($landscape);
    MediaDimensions::store($portrait);

    expect($landscape->refresh()->custom_properties)->toMatchArray(['width' => 1920, 'height' => 1080, 'duration' => 4.5])
        ->and($portrait->refresh()->custom_properties)->toMatchArray(['width' => 1080, 'height' => 1920, 'duration' => 12.35]);
});

it('leaves a video without dimensions when ffprobe is missing or fails', function () {
    $media = addTo($this->folder, UploadedFile::fake()->create('clip.mp4', 10));
    $media->forceFill(['mime_type' => 'video/mp4'])->saveQuietly();

    config()->set('filament-media-manager.dimensions.ffprobe', '/nonexistent/ffprobe');

    expect(MediaDimensions::store($media))->toBeFalse();

    config()->set('filament-media-manager.dimensions.ffprobe', false);

    expect(MediaDimensions::store($media))->toBeFalse()
        ->and($media->refresh()->hasCustomProperty('width'))->toBeFalse();
});

it('measures a real video with ffprobe', function () {
    $file = tempnam(sys_get_temp_dir(), 'video') . '.mp4';
    exec('ffmpeg -v error -f lavfi -i color=c=red:s=64x36:d=1 -pix_fmt yuv420p ' . escapeshellarg($file), result_code: $code);

    $media = $this->folder->addMedia($file)->toMediaCollection($this->folder->collection)->refresh();

    expect($media->mime_type)->toBe('video/mp4')
        ->and($media->custom_properties)->toMatchArray(['width' => 64, 'height' => 36, 'duration' => 1.0]);
})->skip(fn () => ! shell_exec('command -v ffmpeg'), 'ffmpeg is not installed');

it('stores the dimensions of media added before with the command', function () {
    $media = addTo($this->folder, UploadedFile::fake()->image('photo.jpg', 40, 30));
    $media->forgetCustomProperty('width')->forgetCustomProperty('height');
    $media->saveQuietly();
    $measured = addTo($this->folder, UploadedFile::fake()->image('measured.jpg', 10, 10));
    $measured->setCustomProperty('width', 999)->saveQuietly();

    artisan('filament-media-manager:dimensions')->assertSuccessful()->expectsOutputToContain('Stored the dimensions of 1 media.');

    expect($media->refresh()->custom_properties)->toMatchArray(['width' => 40, 'height' => 30])
        ->and($measured->refresh()->getCustomProperty('width'))->toBe(999);

    artisan('filament-media-manager:dimensions --force')->assertSuccessful();

    expect($measured->refresh()->getCustomProperty('width'))->toBe(10);
});
