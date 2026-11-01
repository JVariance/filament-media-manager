<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');

    $this->folder = Folder::factory()->create();
});

function uploadVideo(Folder $folder): Media
{
    $media = $folder->addMedia(UploadedFile::fake()->create('clip.mp4', 64))->toMediaCollection($folder->collection);

    // A fake file has no video contents to detect the type from
    $media->forceFill(['mime_type' => 'video/mp4'])->save();

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function posterDataUrl(string $type = 'png'): string
{
    $image = imagecreatetruecolor(32, 18);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));

    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image);

    return 'data:image/' . $type . ';base64,' . base64_encode((string) ob_get_clean());
}

function editVideo(Folder $folder, Media $media, mixed $poster)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->mountAction('editMedia', ['record' => ['id' => $media->id]])
        ->setActionData(['poster' => $poster])
        ->callMountedAction();
}

function posterFiles(Media $media): array
{
    return Storage::disk('public')->files($media->id . '/posters');
}

it('stores a frame or image sent as the poster of a video', function () {
    $video = uploadVideo($this->folder);

    editVideo($this->folder, $video, posterDataUrl())->assertHasNoActionErrors();

    $video->refresh();

    expect(posterFiles($video))->toHaveCount(1)
        ->and(posterFiles($video)[0])->toEndWith('.png')
        ->and($video->getPosterUrl())->toEndWith(posterFiles($video)[0]);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->assertSeeHtml('poster="' . $video->getPosterUrl() . '"');
});

it('shows the current poster in the form and keeps it when saved unchanged', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::storeDataUrl($video, posterDataUrl());
    $url = $video->refresh()->getPosterUrl();

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->mountAction('editMedia', ['record' => ['id' => $video->id]])
        ->assertActionDataSet(['poster' => $url])
        ->callMountedAction();

    expect($video->refresh()->getPosterUrl())->toBe($url)
        ->and(posterFiles($video))->toHaveCount(1);
});

it('replaces the poster and removes the old image', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::storeDataUrl($video, posterDataUrl());
    $old = posterFiles($video->refresh())[0];

    editVideo($this->folder, $video, posterDataUrl('jpeg'));

    expect(posterFiles($video->refresh()))->toHaveCount(1)
        ->and(posterFiles($video)[0])->not->toBe($old)->toEndWith('.jpg');
});

it('removes the poster', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::storeDataUrl($video, posterDataUrl());

    editVideo($this->folder, $video, null);

    expect($video->refresh()->getPosterUrl())->toBeNull()
        ->and($video->hasCustomProperty('poster'))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->id . '/posters'))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->getPathRelativeToRoot()))->toBeTrue();
});

it('rejects a poster that is not an image', function () {
    $video = uploadVideo($this->folder);

    editVideo($this->folder, $video, 'data:image/png;base64,' . base64_encode('<svg onload="alert(1)"></svg>'))
        ->assertHasActionErrors(['poster']);

    expect($video->refresh()->getPosterUrl())->toBeNull()
        ->and(posterFiles($video))->toBe([]);
});

it('offers a poster only for videos', function () {
    $image = $this->folder->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection($this->folder->collection);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->mountAction('editMedia', ['record' => ['id' => $image->id]])
        ->assertFormFieldHidden('poster');
});

it('removes the poster with the video', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::storeDataUrl($video, posterDataUrl());

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->callAction('deleteMedia', arguments: ['record' => ['id' => $video->id]]);

    expect(Storage::disk('public')->exists($video->id . '/posters'))->toBeFalse();
});

it('serves a video from the panel, with range requests for seeking', function () {
    $video = uploadVideo($this->folder);
    Storage::disk('public')->put($video->getPathRelativeToRoot(), str_repeat('0123456789', 100));
    $url = filament()->getPanel('admin')->route('media-manager.media.source', ['media' => $video->id]);

    get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
    get($url, ['Range' => 'bytes=0-9'])->assertStatus(206)->assertHeader('Content-Length', '10');
});

it('serves only videos, and only to users of the panel', function () {
    $image = $this->folder->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection($this->folder->collection);
    $video = uploadVideo($this->folder);
    $panel = filament()->getPanel('admin');

    get($panel->route('media-manager.media.source', ['media' => $image->id]))->assertNotFound();

    auth()->logout();

    get($panel->route('media-manager.media.source', ['media' => $video->id]))->assertRedirect();
});
