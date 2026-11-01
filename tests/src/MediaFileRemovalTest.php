<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');
});

it('removes the file of a medium deleted in the media list', function () {
    $folder = Folder::factory()->create();
    $deleted = $folder->addMedia(UploadedFile::fake()->image('deleted.jpg'))->toMediaCollection($folder->collection);
    $kept = $folder->addMedia(UploadedFile::fake()->image('kept.jpg'))->toMediaCollection($folder->collection);

    Storage::disk('public')->assertExists($deleted->getPathRelativeToRoot());

    Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->callAction('deleteMedia', arguments: ['record' => ['id' => $deleted->id]]);

    expect(Media::query()->withoutGlobalScopes()->find($deleted->id))->toBeNull();
    Storage::disk('public')->assertMissing($deleted->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($kept->getPathRelativeToRoot());
});

it('removes the files of media deleted through the table', function () {
    $folder = Folder::factory()->create();
    $deleted = $folder->addMedia(UploadedFile::fake()->image('deleted.jpg'))->toMediaCollection($folder->collection);

    Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->selectTableRecords([$deleted->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    Storage::disk('public')->assertMissing($deleted->getPathRelativeToRoot());
});

it('observes the package media model once', function () {
    expect(collect(app('events')->getRawListeners()['eloquent.deleted: ' . Media::class] ?? [])->count())->toBe(1);
});
