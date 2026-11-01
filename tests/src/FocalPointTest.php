<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Form\FocalPointPicker;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\FocalPoint;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');

    $this->folder = Folder::factory()->create();
});

function focalMedia(Folder $folder, UploadedFile $file, ?string $mimeType = null): Media
{
    $media = $folder->addMedia($file)->toMediaCollection($folder->collection);

    if ($mimeType) {
        $media->forceFill(['mime_type' => $mimeType])->save();
    }

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function focalGallery(Folder $folder)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])->test(ListMedia::class);
}

it('sets the focal point of an image and crops the thumbnail around it', function () {
    $image = focalMedia($this->folder, UploadedFile::fake()->image('photo.jpg', 200, 100));

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $image->id]])
        ->assertActionDataSet(['focal' => null])
        ->setActionData(['focal' => ['x' => 25, 'y' => 70]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($image->refresh()->getCustomProperty('focal'))->toBe(['x' => 25, 'y' => 70])
        ->and(FocalPoint::style($image))->toBe('object-position: 25% 70%;');

    focalGallery($this->folder)->assertSeeHtml('style="object-position: 25% 70%;"');
});

it('shows the focal point in the form and resets it to the center', function () {
    $image = focalMedia($this->folder, UploadedFile::fake()->image('photo.jpg'));
    $image->setCustomProperty('focal', ['x' => 10, 'y' => 90])->save();

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $image->id]])
        ->assertActionDataSet(['focal' => ['x' => 10, 'y' => 90]])
        ->setActionData(['focal' => null])
        ->callMountedAction();

    expect($image->refresh()->hasCustomProperty('focal'))->toBeFalse()
        ->and(FocalPoint::style($image))->toBeNull();
});

it('keeps a focal point sent from the browser inside the picture', function () {
    $image = focalMedia($this->folder, UploadedFile::fake()->image('photo.jpg'));

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $image->id]])
        ->setActionData(['focal' => ['x' => 150, 'y' => -3.6]])
        ->callMountedAction();

    expect($image->refresh()->getCustomProperty('focal'))->toBe(['x' => 100, 'y' => 0]);
});

it('sets the focal point of a video on its poster, or on the video without one', function () {
    $video = focalMedia($this->folder, UploadedFile::fake()->create('clip.mp4', 10), 'video/mp4');

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $video->id]])
        ->assertFormFieldVisible('focal')
        ->assertFormFieldExists('focal', fn (FocalPointPicker $field): bool => ($field->getImageUrl() === null) && ($field->getVideoUrl() === $video->getUrl() . '#t=0.1'));

    $image = imagecreatetruecolor(16, 9);
    ob_start();
    imagepng($image);
    VideoPoster::store($video, (string) ob_get_clean());

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $video->id]])
        ->assertFormFieldExists('focal', fn (FocalPointPicker $field): bool => $field->getImageUrl() === $video->refresh()->getPosterUrl())
        ->setActionData(['focal' => ['x' => 60, 'y' => 40]])
        ->callMountedAction();

    expect(FocalPoint::get($video->refresh()))->toBe(['x' => 60, 'y' => 40]);

    focalGallery($this->folder)->assertSeeHtml('style="object-position: 60% 40%;"');
});

it('offers no focal point for other files', function () {
    $pdf = focalMedia($this->folder, UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), 'application/pdf');

    focalGallery($this->folder)
        ->mountAction('editMedia', ['record' => ['id' => $pdf->id]])
        ->assertFormFieldHidden('focal');
});
