<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->private = Folder::factory()->create(['collection' => 'private', 'is_protected' => true, 'password' => 'secret']);
    $this->below = Folder::factory()->create([
        'collection' => 'below',
        'is_protected' => false,
        'model_type' => Folder::class,
        'model_id' => $this->private->id,
    ]);
});

it('does not open a locked folder that the browser sets as the open folder', function () {
    livewire(MediaPicker::class, ['pickerKey' => 'picker'])
        ->set('currentFolderId', $this->private->id)
        ->assertSet('currentFolderId', null);
});

it('does not open a folder below a locked folder that the browser sets as the open folder', function () {
    livewire(MediaPicker::class, ['pickerKey' => 'picker'])
        ->set('currentFolderId', $this->below->id)
        ->assertSet('currentFolderId', null);
});

it('opens the folders below a folder opened with its password', function () {
    livewire(MediaPicker::class, ['pickerKey' => 'picker'])
        ->call('openFolder', $this->private->id)
        ->fillForm(['password' => 'secret'])
        ->callMountedAction()
        ->set('currentFolderId', $this->below->id)
        ->assertSet('currentFolderId', $this->below->id);
});

it('still goes back to the top level from the first breadcrumb', function () {
    $open = Folder::factory()->create(['collection' => 'open', 'is_protected' => false]);

    livewire(MediaPicker::class, ['pickerKey' => 'picker'])
        ->call('openFolder', $open->id)
        ->set('currentFolderId', null)
        ->assertSet('currentFolderId', null);
});
