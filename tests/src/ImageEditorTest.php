<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Events\MediaFileReplaced;
use TomatoPHP\FilamentMediaManager\Form\ImageEditorField;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\ImageEditor;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');

    $this->folder = Folder::factory()->create();
});

function editableImage(Folder $folder, int $width = 120, int $height = 80, string $type = 'jpg'): Media
{
    $media = $folder->addMedia(UploadedFile::fake()->image("photo.{$type}", $width, $height))->toMediaCollection($folder->collection);

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function imageContents(int $width, int $height, string $type = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));

    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image);

    return (string) ob_get_clean();
}

function storedSize(Media $media, ?string $path = null): array
{
    $size = getimagesizefromstring(Storage::disk('public')->get($path ?? $media->getPathRelativeToRoot()));

    return [$size[0], $size[1], $size['mime']];
}

/**
 * As the editor does: upload the image drawn on the canvas, then call the action with the mode
 */
function saveEdited(Folder $folder, Media $media, string $contents, string $mode = 'replace', string $name = 'image.jpg')
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->mountAction('editImage', ['record' => ['id' => $media->id]])
        ->set('componentFileAttachments.mountedActions.0.data.image', UploadedFile::fake()->createWithContent($name, $contents))
        ->setActionData(['image' => ImageEditorField::UPLOADED])
        ->callMountedAction(['mode' => $mode]);
}

it('replaces the image with the edited one and keeps its original', function () {
    Event::fake([MediaFileReplaced::class]);

    $image = editableImage($this->folder);
    $image->setCustomProperty('width', 120)->setCustomProperty('height', 80)->setCustomProperty('focal', ['x' => 10, 'y' => 10])->save();
    $file = $image->file_name;
    $url = $image->getVersionedUrl();

    $this->travel(1)->seconds();

    saveEdited($this->folder, $image, imageContents(50, 40))
        ->assertHasNoActionErrors()
        ->assertNotified('Image saved');

    $image->refresh();

    expect($image->file_name)->toBe($file)
        ->and(storedSize($image))->toBe([50, 40, 'image/jpeg'])
        ->and(storedSize($image, $image->id . '/originals/' . $file))->toBe([120, 80, 'image/jpeg'])
        ->and($image->size)->toBe(strlen(Storage::disk('public')->get($image->getPathRelativeToRoot())))
        ->and($image->custom_properties)->toMatchArray(['width' => 50, 'height' => 40, 'original' => $file])
        ->and($image->hasCustomProperty('focal'))->toBeFalse()
        ->and($image->getVersionedUrl())->not->toBe($url);

    Event::assertDispatched(MediaFileReplaced::class, fn (MediaFileReplaced $event): bool => $event->media->is($image));
});

it('keeps the first original over several edits and restores it', function () {
    $image = editableImage($this->folder);

    saveEdited($this->folder, $image, imageContents(60, 60));
    saveEdited($this->folder, $image->refresh(), imageContents(30, 30));

    expect(storedSize($image->refresh()))->toBe([30, 30, 'image/jpeg']);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->callAction([
            TestAction::make('editImage')->arguments(['record' => ['id' => $image->id]]),
            TestAction::make('restoreOriginal'),
        ])
        ->assertNotified('Original restored');

    $image->refresh();

    expect(storedSize($image))->toBe([120, 80, 'image/jpeg'])
        ->and(ImageEditor::hasOriginal($image))->toBeFalse()
        ->and(Storage::disk('public')->exists($image->id . '/originals'))->toBeFalse();
});

it('finds the original after the file of the image was renamed', function () {
    $image = editableImage($this->folder);
    saveEdited($this->folder, $image, imageContents(60, 60));

    // As Spatie does when the file name changes
    $image->refresh();
    Storage::disk('public')->move($image->getPathRelativeToRoot(), "{$image->id}/renamed.jpg");
    $image->forceFill(['file_name' => 'renamed.jpg'])->save();

    expect(ImageEditor::restoreOriginal($image))->toBeTrue()
        ->and(storedSize($image->refresh()))->toBe([120, 80, 'image/jpeg']);
});

it('finds an original kept before its file name was stored', function () {
    $image = editableImage($this->folder);
    saveEdited($this->folder, $image, imageContents(60, 60));
    $image->refresh()->setCustomProperty('original', true)->save();

    expect(ImageEditor::restoreOriginal($image))->toBeTrue()
        ->and(storedSize($image->refresh()))->toBe([120, 80, 'image/jpeg']);
});

it('saves the edited image as a copy next to the image', function () {
    $image = editableImage($this->folder);
    $image->setCustomProperty('alt', 'A blue sky')->save();

    saveEdited($this->folder, $image, imageContents(64, 36), mode: 'copy')
        ->assertNotified('Saved as a copy');

    $copy = Media::query()->withoutGlobalScopes()->whereKeyNot($image->id)->sole();

    expect($copy)
        ->name->toBe($image->name . ' (edited)')
        ->model_id->toBe($image->model_id)
        ->collection_name->toBe($image->collection_name)
        ->and($copy->getCustomProperty('alt'))->toBe('A blue sky')
        ->and(storedSize($copy))->toBe([64, 36, 'image/jpeg'])
        ->and(storedSize($image->refresh()))->toBe([120, 80, 'image/jpeg'])
        ->and(ImageEditor::hasOriginal($image))->toBeFalse();

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->assertSee($image->name . ' (edited)');
});

it('keeps the type of the image when the browser sends another one', function () {
    $image = editableImage($this->folder);

    // e.g. Safari writes PNG when asked for WebP
    saveEdited($this->folder, $image, imageContents(40, 20, 'png'), name: 'image.png')->assertHasNoActionErrors();

    expect(storedSize($image->refresh()))->toBe([40, 20, 'image/jpeg'])
        ->and($image->mime_type)->toBe('image/jpeg');
});

it('rejects an upload that is not an image', function () {
    $image = editableImage($this->folder);

    saveEdited($this->folder, $image, '<?php echo "hello";')->assertHasActionErrors(['image']);

    expect(storedSize($image->refresh()))->toBe([120, 80, 'image/jpeg'])
        ->and(ImageEditor::hasOriginal($image))->toBeFalse();
});

it('offers the editor only for JPEG, PNG and WebP images', function () {
    $png = editableImage($this->folder, type: 'png');
    $gif = editableImage($this->folder, type: 'gif');
    $video = editableImage($this->folder);
    $video->forceFill(['mime_type' => 'video/mp4'])->save();

    $page = Livewire::withQueryParams(['folder_id' => $this->folder->id])->test(ListMedia::class);

    $page->assertActionVisible(TestAction::make('editImage')->arguments(['record' => ['id' => $png->id]]))
        ->assertActionHidden(TestAction::make('editImage')->arguments(['record' => ['id' => $gif->id]]))
        ->assertActionHidden(TestAction::make('editImage')->arguments(['record' => ['id' => $video->id]]));
});

it('lets a long file name in the heading break', function () {
    $image = $this->folder->addMedia(UploadedFile::fake()->image('EinSehrLangerDateinameOhneBindestriche.jpg'))->toMediaCollection($this->folder->collection);

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->mountAction('editImage', ['record' => ['id' => $image->id]])
        ->assertMountedActionModalSeeHtml('Edit image: <span style="overflow-wrap: anywhere;">EinSehrLangerDateinameOhneBindestriche.jpg</span>');
});

it('removes the kept original with the image', function () {
    $image = editableImage($this->folder);
    saveEdited($this->folder, $image, imageContents(60, 60));

    Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->callAction('deleteMedia', arguments: ['record' => ['id' => $image->id]]);

    expect(Storage::disk('public')->exists($image->id . '/originals'))->toBeFalse();
});

it('serves the image from the panel for the editor, with its Cropper.js assets', function () {
    $image = editableImage($this->folder);

    get(filament()->getPanel('admin')->route('media-manager.media.source', ['media' => $image->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect(FilamentAsset::getScriptSrc('cropper', 'tomatophp/filament-media-manager'))->toContain('cropper')
        ->and(FilamentAsset::getStyleHref('cropper', 'tomatophp/filament-media-manager'))->toContain('cropper')
        ->and(file_exists(__DIR__ . '/../../resources/dist/cropper/cropper.min.js'))->toBeTrue();
});
