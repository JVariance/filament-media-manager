<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\MediaMover;
use TomatoPHP\FilamentMediaManager\Tests\Models\Product;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();

    actingAs($this->user);

    $this->panel->resources([
        FolderResource::class,
        MediaResource::class,
    ]);

    /** @var FilamentMediaManagerPlugin $plugin */
    $plugin = filament()->getPanel('admin')->getPlugin('filament-media-manager');
    $plugin->allowSubFolders();

    Storage::fake('public');
});

function dropUpload(Folder $folder, string $name): Media
{
    $media = ($folder->model ?? $folder)
        ->addMedia(UploadedFile::fake()->image("{$name}.jpg"))
        ->toMediaCollection($folder->collection);

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function dropGallery(Folder $folder)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])->test(ListMedia::class);
}

it('makes media draggable and the sub folders drop targets', function () {
    $folder = Folder::factory()->create();
    $sub = Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $folder->id]);
    $other = Folder::factory()->create();
    dropUpload($folder, 'photo');

    dropGallery($folder)
        ->assertSeeHtml('x-on:dragstart="startMedia($event')
        ->assertSeeHtml('data-media-drop-folder="' . $sub->id . '"')
        // The breadcrumbs and other folders are matched against these ids in the browser
        ->assertSeeHtml('data-drop-folder-ids="' . e(json_encode(dropTargetIds($folder))) . '"')
        ->assertSeeHtml('draggable="false"');

    expect(dropTargetIds($folder))->toContain($sub->id, $other->id)->not->toContain($folder->id);
});

function dropTargetIds(Folder $folder): array
{
    return MediaMover::targets($folder)->pluck('id')->all();
}

it('moves the dropped media to the folder', function () {
    $folder = Folder::factory()->create();
    $sub = Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $folder->id, 'name' => 'Sub']);
    $first = dropUpload($folder, 'first');
    $second = dropUpload($folder, 'second');
    $stays = dropUpload($folder, 'stays');

    dropGallery($folder)
        ->call('moveMediaToFolder', [$first->getKey(), (string) $second->getKey()], $sub->id)
        ->assertNotified('2 media moved to Sub')
        ->assertDispatched('deselectAllTableRecords');

    expect($first->refresh()->collection_name)->toBe($sub->collection)
        ->and($second->refresh()->collection_name)->toBe($sub->collection)
        ->and($stays->refresh()->collection_name)->toBe($folder->collection);

    dropGallery($sub)->assertCanSeeTableRecords([$first, $second]);
});

it('moves the dropped media up to the parent folder of the breadcrumbs', function () {
    $parent = Folder::factory()->create();
    $sub = Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $parent->id]);
    $media = dropUpload($sub, 'photo');

    dropGallery($sub)->call('moveMediaToFolder', [$media->getKey()], $parent->id);

    expect($media->refresh())
        ->model_id->toBe($parent->id)
        ->collection_name->toBe($parent->collection);
});

it('moves only media of the open folder', function () {
    $folder = Folder::factory()->create();
    $elsewhere = Folder::factory()->create();
    $target = Folder::factory()->create();
    $foreign = dropUpload($elsewhere, 'foreign');

    dropGallery($folder)->call('moveMediaToFolder', [$foreign->getKey()], $target->id);

    expect($foreign->refresh()->collection_name)->toBe($elsewhere->collection);
});

it('moves nothing onto a folder of a model record', function () {
    $folder = Folder::factory()->create();
    $product = Product::query()->create(['name' => 'Chair']);
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);
    $media = dropUpload($folder, 'photo');

    dropGallery($folder)
        ->call('moveMediaToFolder', [$media->getKey()], $record->id)
        ->assertNotNotified();

    expect($media->refresh()->collection_name)->toBe($folder->collection);
});

it('offers no dragging in the folder of a model record', function () {
    $product = Product::query()->create(['name' => 'Chair']);
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);
    $media = dropUpload($record, 'chair');
    $target = Folder::factory()->create();

    dropGallery($record)
        ->assertDontSeeHtml('x-on:dragstart="startMedia($event')
        ->call('moveMediaToFolder', [$media->getKey()], $target->id);

    expect($media->refresh()->model_type)->toBe(Product::class);
});
