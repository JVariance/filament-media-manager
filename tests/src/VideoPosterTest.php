<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Form\VideoPosterPicker;
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

function posterImage(string $type = 'png', int $width = 32, int $height = 18): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));

    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image);

    return (string) ob_get_clean();
}

function posterUpload(string $type = 'png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent("poster.{$type}", posterImage($type));
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

    editVideo($this->folder, $video, posterUpload())->assertHasNoActionErrors();

    $video->refresh();

    expect(posterFiles($video))->toHaveCount(1)
        ->and(posterFiles($video)[0])->toEndWith('.png')
        ->and($video->getPosterUrl())->toEndWith(posterFiles($video)[0]);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->assertSeeHtml('poster="' . $video->getPosterUrl() . '"');
});

it('stores a poster uploaded by the browser to the file attachments, as the picker does', function () {
    $video = uploadVideo($this->folder);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->mountAction('editMedia', ['record' => ['id' => $video->id]])
        ->set('componentFileAttachments.mountedActions.0.data.poster', posterUpload('jpeg'))
        ->setActionData(['poster' => VideoPosterPicker::UPLOADED])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(posterFiles($video->refresh()))->toHaveCount(1)
        ->and(posterFiles($video)[0])->toEndWith('.jpg');
});

it('rejects a file uploaded to the attachments that is not an image', function () {
    $video = uploadVideo($this->folder);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->mountAction('editMedia', ['record' => ['id' => $video->id]])
        ->set('componentFileAttachments.mountedActions.0.data.poster', UploadedFile::fake()->createWithContent('poster.jpg', 'not an image'))
        ->setActionData(['poster' => VideoPosterPicker::UPLOADED])
        ->callMountedAction()
        ->assertHasActionErrors(['poster']);

    expect(posterFiles($video->refresh()))->toBe([]);
});

it('takes a poster larger than a Livewire request may be', function () {
    $video = uploadVideo($this->folder);

    // Noise compresses badly, so the image is larger than the payload limit of 1 MB
    $image = imagecreatetruecolor(1920, 1080);

    for ($i = 0; $i < 400000; $i++) {
        imagesetpixel($image, random_int(0, 1919), random_int(0, 1079), random_int(0, 0xFFFFFF));
    }

    ob_start();
    imagepng($image);
    $contents = (string) ob_get_clean();

    expect(strlen($contents))->toBeGreaterThan(config('livewire.payload.max_size', 1024 * 1024));

    editVideo($this->folder, $video, UploadedFile::fake()->createWithContent('poster.png', $contents))->assertHasNoActionErrors();

    expect(posterFiles($video->refresh()))->toHaveCount(1);
});

it('shows the current poster in the form and keeps it when saved unchanged', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::store($video, posterImage());
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
    VideoPoster::store($video, posterImage());
    $old = posterFiles($video->refresh())[0];

    editVideo($this->folder, $video, posterUpload('jpeg'));

    expect(posterFiles($video->refresh()))->toHaveCount(1)
        ->and(posterFiles($video)[0])->not->toBe($old)->toEndWith('.jpg');
});

it('removes the poster', function () {
    $video = uploadVideo($this->folder);
    VideoPoster::store($video, posterImage());

    editVideo($this->folder, $video, null);

    expect($video->refresh()->getPosterUrl())->toBeNull()
        ->and($video->hasCustomProperty('poster'))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->id . '/posters'))->toBeFalse()
        ->and(Storage::disk('public')->exists($video->getPathRelativeToRoot()))->toBeTrue();
});

it('rejects a poster that is not an image', function () {
    $video = uploadVideo($this->folder);

    editVideo($this->folder, $video, UploadedFile::fake()->createWithContent('poster.png', '<svg onload="alert(1)"></svg>'))
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
    VideoPoster::store($video, posterImage());

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
