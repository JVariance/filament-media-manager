<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;
use TomatoPHP\FilamentMediaManager\Tests\Support\PickerForm;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());

    $this->folder = Folder::factory()->create(['collection' => 'photos']);
});

function gridPhoto(Folder $folder, string $name): string
{
    return $folder->addMedia(UploadedFile::fake()->image("{$name}.jpg"))->toMediaCollection('photos')->uuid;
}

it('shows the picked media as tiles, in the order of the state', function () {
    $uuids = [gridPhoto($this->folder, 'dune'), gridPhoto($this->folder, 'beach'), gridPhoto($this->folder, 'cliff')];

    $html = livewire(PickerForm::class, ['initial' => $uuids])
        ->assertSeeHtml('class="fi-picker-grid"')
        ->assertSeeInOrder(['dune', 'beach', 'cliff'])
        ->html();

    expect(substr_count($html, 'class="fi-picker-grid-item"'))->toBe(3);
});

it('drags the tiles by their preview only when there are several to sort', function () {
    livewire(PickerForm::class, ['initial' => [gridPhoto($this->folder, 'dune'), gridPhoto($this->folder, 'beach')]])
        ->assertSeeHtml('class="fi-picker-preview-thumb fi-picker-drag-handle"');

    livewire(PickerForm::class, ['initial' => [gridPhoto($this->folder, 'cliff')]])
        ->assertDontSeeHtml('fi-picker-preview-thumb fi-picker-drag-handle');

    livewire(PickerForm::class, ['single' => true, 'initial' => gridPhoto($this->folder, 'reef')])
        ->assertSeeHtml('class="fi-picker-grid-item"')
        ->assertDontSeeHtml('fi-picker-preview-thumb fi-picker-drag-handle');
});
