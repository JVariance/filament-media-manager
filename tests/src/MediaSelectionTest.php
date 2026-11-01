<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Actions\Testing\TestAction;
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

    Storage::fake('public');
});

function selectionPlugin(): FilamentMediaManagerPlugin
{
    /** @var FilamentMediaManagerPlugin $plugin */
    $plugin = filament()->getPanel('admin')->getPlugin('filament-media-manager');

    return $plugin;
}

/**
 * Like the "Add Media" action: a sub folder's media belong to its parent folder
 */
function uploadTo(Folder $folder, string $name): Media
{
    /** @var Media $media */
    $media = ($folder->model ?? $folder)
        ->addMedia(UploadedFile::fake()->image("{$name}.jpg"))
        ->toMediaCollection($folder->collection);

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

function moveAction(): TestAction
{
    return TestAction::make('moveMedia')->table()->bulk();
}

function gallery(Folder $folder)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])->test(ListMedia::class);
}

it('renders a selection checkbox for each medium', function () {
    $folder = Folder::factory()->create();
    $media = uploadTo($folder, 'photo');

    gallery($folder)
        ->assertSeeHtml('value="' . $media->getKey() . '"')
        ->assertSeeHtml('fi-ta-record-checkbox fi-checkbox-input media-item-checkbox');
});

it('moves the selected media to a folder at the top level', function () {
    $from = Folder::factory()->create(['name' => 'Inbox']);
    $to = Folder::factory()->create(['name' => 'Archive']);
    $first = uploadTo($from, 'first');
    $second = uploadTo($from, 'second');
    $stays = uploadTo($from, 'stays');

    gallery($from)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(moveAction(), data: ['folder' => $to->id])
        ->assertHasNoFormErrors()
        ->assertNotified('2 media moved to Archive');

    foreach ([$first, $second] as $media) {
        expect($media->refresh())
            ->model_type->toBe(Folder::class)
            ->model_id->toBe($to->id)
            ->collection_name->toBe($to->collection);
    }

    expect($stays->refresh()->collection_name)->toBe($from->collection);

    gallery($to)->assertCanSeeTableRecords([$first, $second])->assertCanNotSeeTableRecords([$stays]);
});

it('moves media into a sub folder like an upload to it', function () {
    selectionPlugin()->allowSubFolders();

    $from = Folder::factory()->create();
    $parent = Folder::factory()->create();
    $sub = Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $parent->id]);
    $media = uploadTo($from, 'photo');
    $uploaded = uploadTo($sub, 'uploaded');

    gallery($from)
        ->selectTableRecords([$media->getKey()])
        ->callAction(moveAction(), data: ['folder' => $sub->id]);

    expect($media->refresh())
        ->model_type->toBe($uploaded->model_type)
        ->model_id->toBe($uploaded->model_id)
        ->collection_name->toBe($sub->collection)
        ->order_column->toBeGreaterThan($uploaded->order_column);

    gallery($sub)->assertCanSeeTableRecords([$media, $uploaded]);
});

it('moves media out of a sub folder', function () {
    selectionPlugin()->allowSubFolders();

    $parent = Folder::factory()->create();
    $sub = Folder::factory()->create(['model_type' => Folder::class, 'model_id' => $parent->id]);
    $media = uploadTo($sub, 'photo');

    gallery($sub)
        ->selectTableRecords([$media->getKey()])
        ->callAction(moveAction(), data: ['folder' => $parent->id]);

    expect($media->refresh())
        ->model_id->toBe($parent->id)
        ->collection_name->toBe($parent->collection);
});

it('offers only folders that hold folder media as targets', function () {
    $from = Folder::factory()->create();
    $other = Folder::factory()->create(['name' => 'Other']);
    $modelType = Folder::factory()->create(['model_type' => Product::class, 'collection' => null]);
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => 1, 'collection' => 'images']);
    uploadTo($from, 'photo');

    $options = fn () => collect(MediaMover::targets($from)->pluck('id'));

    expect($options())
        ->toContain($other->id)
        ->not->toContain($from->id, $modelType->id, $record->id);
});

it('moves nothing to a folder that is not offered', function () {
    $from = Folder::factory()->create();
    $record = Folder::factory()->create(['model_type' => Product::class, 'model_id' => 1, 'collection' => 'images']);
    $media = uploadTo($from, 'photo');

    gallery($from)
        ->selectTableRecords([$media->getKey()])
        ->callAction(moveAction(), data: ['folder' => $record->id]);

    expect($media->refresh())
        ->model_id->toBe($from->id)
        ->collection_name->toBe($from->collection);
});

it('offers no move in the folder of a model record, its media belong to the record', function () {
    $product = Product::query()->create(['name' => 'Chair']);
    $folder = Folder::factory()->create(['model_type' => Product::class, 'model_id' => $product->id, 'collection' => 'images']);
    $media = uploadTo($folder, 'chair');

    gallery($folder)->assertActionHidden(moveAction());

    // Even when sent anyway, media of a record stay with it
    $target = Folder::factory()->create();

    expect(MediaMover::move([$media], $target))->toBe(0)
        ->and($media->refresh()->model_type)->toBe(Product::class);
});

it('lets only the owner move and delete media of a folder with user access', function () {
    selectionPlugin()->allowUserAccess();

    $own = Folder::factory()->create(['user_id' => $this->user->id, 'user_type' => User::class]);
    $foreign = Folder::factory()->create(['user_id' => User::factory()->create()->id, 'user_type' => User::class, 'is_public' => true]);
    uploadTo($own, 'mine');
    uploadTo($foreign, 'theirs');

    gallery($own)
        ->assertActionVisible(moveAction())
        ->assertActionVisible(TestAction::make('delete')->table()->bulk());

    gallery($foreign)
        ->assertActionHidden(moveAction())
        ->assertActionHidden(TestAction::make('delete')->table()->bulk());

    expect(MediaMover::targets($own)->pluck('id'))->not->toContain($foreign->id);
});

it('deletes the selected media', function () {
    $folder = Folder::factory()->create();
    $first = uploadTo($folder, 'first');
    $second = uploadTo($folder, 'second');
    $stays = uploadTo($folder, 'stays');

    gallery($folder)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(Media::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$stays->getKey()]);
});

it('translates the move notification', function (string $locale, string $expected) {
    app()->setLocale($locale);

    $from = Folder::factory()->create();
    $to = Folder::factory()->create(['name' => 'Archive']);
    $media = uploadTo($from, 'photo');

    gallery($from)
        ->selectTableRecords([$media->getKey()])
        ->callAction(moveAction(), data: ['folder' => $to->id])
        ->assertNotified($expected);
})->with([
    ['en', '1 medium moved to Archive'],
    ['tr', '1 medya Archive klasörüne taşındı'],
    ['ru', '1 медиафайл перемещён в Archive'],
]);
