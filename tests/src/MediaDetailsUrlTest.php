<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;

it('links the file in the details, to open in a new tab', function () {
    actingAs(User::factory()->create());
    $this->panel->resources([MediaResource::class]);
    Storage::fake('public');

    $folder = Folder::factory()->create();
    $media = $folder->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection($folder->collection);

    Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->assertSeeHtml('<a href="' . $media->getUrl() . '" target="_blank" rel="noopener" class="media-text-sm media-link">')
        ->assertSee('URL');
});
