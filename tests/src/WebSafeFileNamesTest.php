<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Events\MediaFileRenamed;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\WebSafeFileName;
use TomatoPHP\FilamentMediaManager\Tests\Models\Product;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');

    $this->folder = Folder::factory()->create();
});

function fileNamesPlugin(): FilamentMediaManagerPlugin
{
    /** @var FilamentMediaManagerPlugin $plugin */
    $plugin = filament()->getPanel('admin')->getPlugin('filament-media-manager');

    return $plugin;
}

function uploadToGallery(Folder $folder, string $fileName): Media
{
    Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->mountAction('create_media')
        ->setActionData(['file' => [UploadedFile::fake()->image($fileName)]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    return Media::query()->latest('id')->firstOrFail();
}

it('transliterates in the language, lowercase, with hyphens', function (string $language, string $fileName, string $expected) {
    expect(WebSafeFileName::make($fileName, $language))->toBe($expected);
})->with([
    ['de', 'Ärger & Spaß.JPG', 'aerger-und-spass.jpg'],
    ['de', 'Größe Übersicht ÖL.png', 'groesse-uebersicht-oel.png'],
    ['de', 'Café (Kopie) #2 @ Büro.webp', 'cafe-kopie-2-at-buero.webp'],
    ['en', 'Ärger & Spaß.JPG', 'arger-and-spass.jpg'],
    ['tr', 'Ağaç & Çiçek.jpg', 'agac-ve-cicek.jpg'],
    ['de', '«».PDF', 'file.pdf'],
    ['de', 'README', 'readme'],
]);

it('takes words for further characters and a language from the plugin', function () {
    fileNamesPlugin()->webSafeFileNames(dictionary: ['+' => 'plus'], language: 'de');

    app()->setLocale('en');

    expect(WebSafeFileName::make('Größe+Breite.jpg'))->toBe('groesse-plus-breite.jpg');
});

it('names uploads to the media manager web-safe when the plugin says so, and keeps their names', function () {
    fileNamesPlugin()->webSafeFileNames();
    app()->setLocale('de');

    $media = uploadToGallery($this->folder, 'Ärger & Spaß.JPG');

    expect($media->file_name)->toBe('aerger-und-spass.jpg')
        ->and($media->name)->toBe('Ärger & Spaß')
        ->and(Storage::disk('public')->exists("{$media->id}/aerger-und-spass.jpg"))->toBeTrue();
});

it('numbers a file name that a medium in the folder has already', function () {
    fileNamesPlugin()->webSafeFileNames();
    app()->setLocale('de');

    uploadToGallery($this->folder, 'Spaß.jpg');

    expect(uploadToGallery($this->folder, 'SPASS.jpg')->file_name)->toBe('spass-2.jpg')
        ->and(uploadToGallery($this->folder, 'spaß.jpg')->file_name)->toBe('spass-3.jpg')
        ->and(uploadToGallery(Folder::factory()->create(), 'Spaß.jpg')->file_name)->toBe('spass.jpg');
});

it('leaves the names of uploads as they are by default', function () {
    expect(uploadToGallery($this->folder, 'Ärger & Spaß.JPG')->file_name)->toBe('Ärger-&-Spaß.JPG');
});

it('names uploads in the picker web-safe', function () {
    fileNamesPlugin()->webSafeFileNames();
    app()->setLocale('de');

    $product = Product::create(['name' => 'Chair']);
    $folder = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);

    Livewire::test(MediaPicker::class, ['pickerKey' => 'picker', 'isMultiple' => true])
        ->set('currentFolderId', $folder->id)
        ->mountAction('uploadMedia')
        ->fillForm(['files' => [UploadedFile::fake()->image('Stuhl für Büro.jpg')]])
        ->callMountedAction();

    expect($product->fresh()->getFirstMedia('images')->file_name)->toBe('stuhl-fuer-buero.jpg');
});

it('renames the files of media added before, with redirects from their old URLs', function (string $format, string $redirect) {
    Event::fake([MediaFileRenamed::class]);
    app()->setLocale('de');

    $media = $this->folder->addMedia(UploadedFile::fake()->image('Ärger & Spaß.JPG'))->toMediaCollection($this->folder->collection);
    $safe = $this->folder->addMedia(UploadedFile::fake()->image('schon-gut.jpg'))->toMediaCollection($this->folder->collection);
    $oldUrl = $media->getUrl();

    artisan("filament-media-manager:web-safe-file-names --redirects={$format}")
        ->expectsOutputToContain('Renamed 1 file(s).')
        ->expectsOutputToContain($redirect)
        ->assertSuccessful();

    $media->refresh();

    expect($media->file_name)->toBe('aerger-und-spass.jpg')
        ->and($media->name)->toBe('Ärger & Spaß')
        ->and(Storage::disk('public')->exists("{$media->id}/aerger-und-spass.jpg"))->toBeTrue()
        ->and(Storage::disk('public')->exists("{$media->id}/Ärger-&-Spaß.JPG"))->toBeFalse()
        ->and($safe->refresh()->file_name)->toBe('schon-gut.jpg');

    Event::assertDispatched(MediaFileRenamed::class, fn (MediaFileRenamed $event): bool => $event->media->is($media)
        && ($event->oldFileName === 'Ärger-&-Spaß.JPG')
        && ($event->oldUrl === $oldUrl)
        && ($event->newUrl === $media->getUrl()));
})->with([
    ['apache', 'Redirect 301 "/storage/1/Ärger-&-Spaß.JPG" "/storage/1/aerger-und-spass.jpg"'],
    ['nginx', 'location = "/storage/1/Ärger-&-Spaß.JPG" { return 301 "/storage/1/aerger-und-spass.jpg"; }'],
]);

it('only shows the new file names in a dry run', function () {
    Event::fake([MediaFileRenamed::class]);
    app()->setLocale('de');

    $media = $this->folder->addMedia(UploadedFile::fake()->image('Größe.png'))->toMediaCollection($this->folder->collection);

    artisan('filament-media-manager:web-safe-file-names --dry-run')
        ->expectsOutputToContain('groesse.png')
        ->expectsOutputToContain('Would rename 1 file(s).')
        ->assertSuccessful();

    expect($media->refresh()->file_name)->toBe('Größe.png')
        ->and(Storage::disk('public')->exists("{$media->id}/Größe.png"))->toBeTrue();

    Event::assertNotDispatched(MediaFileRenamed::class);
});

it('says when all file names are web-safe, and rejects another redirect format', function () {
    $this->folder->addMedia(UploadedFile::fake()->image('gut.jpg'))->toMediaCollection($this->folder->collection);

    artisan('filament-media-manager:web-safe-file-names')->expectsOutput('All file names are web-safe already.')->assertSuccessful();
    artisan('filament-media-manager:web-safe-file-names --redirects=iis')->assertFailed();
});
