<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\FilamentMediaManagerPlugin;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource\Pages\ListFolders;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\FolderMover;
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

    folderMovePlugin()->allowSubFolders();

    Storage::fake('public');
});

function folderMovePlugin(): FilamentMediaManagerPlugin
{
    /** @var FilamentMediaManagerPlugin $plugin */
    $plugin = filament()->getPanel('admin')->getPlugin('filament-media-manager');

    return $plugin;
}

function subFolderOf(Folder $parent, array $attributes = []): Folder
{
    return Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $parent->id, ...$attributes]);
}

/**
 * Like the "Add Media" action: a sub folder's media belong to its parent folder
 */
function uploadInto(Folder $folder, string $name): Media
{
    $media = ($folder->model ?? $folder)
        ->addMedia(UploadedFile::fake()->image("{$name}.jpg"))
        ->toMediaCollection($folder->collection);

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function folderGallery(Folder $folder)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])->test(ListMedia::class);
}

it('moves a sub folder dropped on another folder, with its media', function () {
    $parent = Folder::factory()->create();
    $moved = subFolderOf($parent, ['name' => 'Moved']);
    $nested = subFolderOf($moved);
    $target = Folder::factory()->create(['name' => 'Target']);
    $media = uploadInto($moved, 'photo');
    $nestedMedia = uploadInto($nested, 'nested');
    $parentMedia = uploadInto($parent, 'parent');

    folderGallery($parent)
        ->call('moveFolderToFolder', $moved->id, $target->id)
        ->assertNotified('Moved Moved to Target');

    expect($moved->refresh())
        ->model_type->toBe(Folder::class)
        ->model_id->toBe($target->id)
        ->and($media->refresh())
        ->model_id->toBe($target->id)
        ->collection_name->toBe($moved->collection)
        // The media of its sub folder belong to the folder itself, which kept its id
        ->and($nestedMedia->refresh()->model_id)->toBe($moved->id)
        ->and($parentMedia->refresh()->model_id)->toBe($parent->id);

    folderGallery($moved)->assertCanSeeTableRecords([$media]);
    folderGallery($nested)->assertCanSeeTableRecords([$nestedMedia]);
    folderGallery($parent)->assertCanSeeTableRecords([$parentMedia])->assertCanNotSeeTableRecords([$media]);
});

it('moves a sub folder to the top level, its media then belong to it', function () {
    $parent = Folder::factory()->create();
    $moved = subFolderOf($parent);
    $media = uploadInto($moved, 'photo');

    folderGallery($parent)->call('moveFolderToFolder', $moved->id, FolderMover::TOP_LEVEL);

    expect($moved->refresh())
        ->model_type->toBeNull()
        ->model_id->toBeNull()
        ->and($media->refresh()->model_id)->toBe($moved->id);

    folderGallery($moved)->assertCanSeeTableRecords([$media]);
    Livewire::test(ListFolders::class)->assertSeeHtml('data-media-drop-folder="' . $moved->id . '"');
});

it('moves a folder at the top level dropped on another one in the folders list', function () {
    $moved = Folder::factory()->create(['name' => 'Moved']);
    $target = Folder::factory()->create(['name' => 'Target']);
    $media = uploadInto($moved, 'photo');

    Livewire::test(ListFolders::class)
        ->call('moveFolderToFolder', $moved->id, $target->id)
        ->assertNotified('Moved Moved to Target');

    expect($moved->refresh()->model_id)->toBe($target->id)
        ->and($media->refresh()->model_id)->toBe($target->id);

    folderGallery($target)->assertSeeHtml('data-media-drop-folder="' . $moved->id . '"');
    folderGallery($moved)->assertCanSeeTableRecords([$media]);
});

it('moves the open folder with the header action', function () {
    $parent = Folder::factory()->create();
    $moved = subFolderOf($parent);
    $target = Folder::factory()->create(['name' => 'Target']);

    folderGallery($moved)
        ->mountAction('move_folder')
        ->assertFormFieldExists('target', fn ($field): bool => array_keys($field->getOptions()) === [FolderMover::TOP_LEVEL, ...FolderMover::targets($moved)->orderBy('name')->pluck('id')->all()])
        ->setActionData(['target' => $target->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($moved->refresh()->model_id)->toBe($target->id);
});

it('never moves a folder into itself or one of its sub folders', function () {
    $folder = Folder::factory()->create();
    $child = subFolderOf($folder);
    $grandChild = subFolderOf($child);
    $other = Folder::factory()->create();

    expect(FolderMover::targetIds($folder))
        ->toContain($other->id)
        ->not->toContain($folder->id, $child->id, $grandChild->id, FolderMover::TOP_LEVEL);

    Livewire::test(ListFolders::class)->call('moveFolderToFolder', $folder->id, $grandChild->id);

    expect($folder->refresh()->model_id)->toBeNull();
});

it('offers neither the folder it is in nor folders of models, locked or without access', function () {
    folderMovePlugin()->allowUserAccess();

    $parent = Folder::factory()->create();
    $moved = subFolderOf($parent);
    $product = Product::query()->create(['name' => 'Chair']);
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);
    $locked = Folder::factory()->create(['is_protected' => true, 'password' => 'secret']);
    $foreign = Folder::factory()->create(['user_id' => User::factory()->create()->id, 'user_type' => User::class, 'is_public' => true]);
    $open = Folder::factory()->create();

    expect(FolderMover::targetIds($moved))
        ->toBe([FolderMover::TOP_LEVEL, ...FolderMover::targets($moved)->pluck('id')->all()])
        ->toContain($open->id)
        ->not->toContain($parent->id, $record->id, $locked->id, $foreign->id);

    folderGallery($parent)->call('moveFolderToFolder', $moved->id, $locked->id);

    expect($moved->refresh()->model_id)->toBe($parent->id);
});

it('moves no folder of a model, of another user or without sub folders', function () {
    $product = Product::query()->create(['name' => 'Chair']);
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);
    $folder = Folder::factory()->create();
    $target = Folder::factory()->create();

    expect(FolderMover::targetIds($record))->toBe([]);

    folderMovePlugin()->allowUserAccess();
    $foreign = Folder::factory()->create(['user_id' => User::factory()->create()->id, 'user_type' => User::class, 'is_public' => true]);

    expect(FolderMover::targetIds($foreign))->toBe([]);

    folderMovePlugin()->allowUserAccess(false)->allowSubFolders(false);

    expect(FolderMover::targetIds($folder))->toBe([]);

    Livewire::test(ListFolders::class)->call('moveFolderToFolder', $folder->id, $target->id);

    expect($folder->refresh()->model_id)->toBeNull();
    folderGallery($folder)->assertActionHidden('move_folder');
});

it('only moves sub folders of the open folder from the media list', function () {
    $parent = Folder::factory()->create();
    $elsewhere = subFolderOf(Folder::factory()->create());
    $target = Folder::factory()->create();

    folderGallery($parent)->call('moveFolderToFolder', $elsewhere->id, $target->id);

    expect($elsewhere->refresh()->model_id)->not->toBe($target->id);
});

it('makes movable folders draggable with their targets', function () {
    $parent = Folder::factory()->create();
    $sub = subFolderOf($parent);
    $other = Folder::factory()->create();
    uploadInto($parent, 'photo');

    folderGallery($parent)
        ->assertSeeHtml('data-folder-targets="' . e(json_encode(FolderMover::targetIds($sub))) . '"')
        ->assertSeeHtml('x-on:dragstart="startFolder($event, ' . $sub->id . ')"');

    Livewire::test(ListFolders::class)
        ->assertSeeHtml('x-on:dragstart="startFolder($event, ' . $other->id . ')"')
        ->assertSeeHtml('data-media-drop-folder="' . $parent->id . '"');
});

it('translates the folder notifications', function (string $locale, string $moved, string $top) {
    app()->setLocale($locale);

    $parent = Folder::factory()->create();
    $folder = subFolderOf($parent, ['name' => 'Photos']);
    $target = Folder::factory()->create(['name' => 'Archive']);

    folderGallery($parent)->call('moveFolderToFolder', $folder->id, $target->id)->assertNotified($moved);
    folderGallery($target)->call('moveFolderToFolder', $folder->id, FolderMover::TOP_LEVEL)->assertNotified($top);
})->with([
    ['en', 'Moved Photos to Archive', 'Moved Photos to the top level'],
    ['tr', 'Photos, Archive klasörüne taşındı', 'Photos en üst düzeye taşındı'],
    ['ru', 'Папка Photos перемещена в Archive', 'Папка Photos перемещена на верхний уровень'],
]);
