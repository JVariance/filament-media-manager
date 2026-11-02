<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;
use TomatoPHP\FilamentMediaManager\Tests\Support\PickerForm;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());

    $folder = Folder::factory()->create(['collection' => 'photos']);
    $this->media = $folder->addMedia(UploadedFile::fake()->image('beach.jpg'))->toMediaCollection('photos');
    $this->deleted = '6f1c2a8e-0000-4000-8000-000000000000';
});

it('shows a medium deleted since it was picked in the field, where it can be removed', function () {
    livewire(PickerForm::class, ['initial' => [$this->deleted, $this->media->uuid]])
        ->assertSeeInOrder(['No longer available', 'beach.jpg'])
        ->mountAction(TestAction::make('removeMediaItem')->schemaComponent('media'), ['uuid' => $this->deleted])
        ->callMountedAction()
        ->assertSet('data.media', [$this->media->uuid])
        ->assertDontSee('No longer available');
});

it('shows a medium deleted since it was picked in the selection of the picker, where it can be removed', function () {
    Livewire::test(MediaPicker::class, ['pickerKey' => 'picker', 'initialState' => [$this->deleted, $this->media->uuid]])
        ->assertSeeInOrder(['No longer available', 'beach.jpg'])
        ->call('removeSelection', $this->deleted)
        ->assertSet('selectedMedia', [$this->media->uuid])
        ->assertDontSee('No longer available');
});
