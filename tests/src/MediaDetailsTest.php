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

it('lets a long file name in the details break, so it does not widen a narrow screen', function () {
    actingAs(User::factory()->create());
    $this->panel->resources([MediaResource::class]);
    Storage::fake('public');

    $folder = Folder::factory()->create();
    $media = $folder->addMedia(UploadedFile::fake()->image('EinSehrLangerDateinameOhneBindestriche.jpg'))->toMediaCollection($folder->collection);

    $page = Livewire::withQueryParams(['folder_id' => $folder->id])
        ->test(ListMedia::class)
        ->assertSeeHtml('<span style="overflow-wrap: anywhere;">' . $media->file_name . '</span>')
        ->assertSeeHtml('<span style="overflow-wrap: anywhere;">' . $media->uuid . '</span>');

    // The file name again, in the details below
    expect($page->html())->toMatch('/\.media-text-sm \{[^}]*overflow-wrap: anywhere;/');
});
