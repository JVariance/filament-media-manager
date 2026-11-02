<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());
});

function searchFolder(string $name, array $attributes = [], ?Folder $parent = null): Folder
{
    return Folder::factory()->create([
        'name' => $name,
        'collection' => str($name)->slug()->toString(),
        'model_type' => $parent ? Folder::class : null,
        'model_id' => $parent?->id,
        'is_protected' => false,
        ...$attributes,
    ]);
}

function searchPhoto(Folder $folder, string $name)
{
    // A sub folder keeps its media on its parent, like an upload in the picker does
    $model = $folder->model_type === Folder::class ? Folder::find($folder->model_id) : $folder;

    return $model->addMedia(UploadedFile::fake()->image("{$name}.jpg"))->toMediaCollection($folder->collection);
}

function searchPicker(array $parameters = [])
{
    return livewire(MediaPicker::class, ['pickerKey' => 'picker', ...$parameters]);
}

it('finds media in all folders and sub folders, and names their folder', function () {
    $offers = searchFolder('Offers');
    $students = searchFolder('Students', parent: $offers);
    $other = searchFolder('Other');

    searchPhoto($offers, 'harbour-front');
    searchPhoto($students, 'harbour-room');
    searchPhoto($other, 'garden');

    searchPicker()
        ->set('search', 'harbour')
        ->assertSee(['Search results in all folders', 'harbour-front', 'harbour-room', 'Offers', 'Students'])
        ->assertDontSee('garden');
});

it('searches all folders from inside a folder too', function () {
    $offers = searchFolder('Offers');
    $other = searchFolder('Other');
    searchPhoto($other, 'harbour');

    searchPicker()
        ->set('currentFolderId', $offers->id)
        ->set('search', 'harbour')
        ->assertSee('harbour');
});

it('finds folders of all levels by name', function () {
    $offers = searchFolder('Offers');
    searchFolder('Harbour flats', parent: searchFolder('Rentals', parent: $offers));

    searchPicker()
        ->set('search', 'harbour')
        ->assertSee('Harbour flats')
        ->assertDontSee('Rentals');
});

it('leaves out the media of a locked folder and of the folders below it until it is opened with its password', function () {
    $private = searchFolder('Private', ['is_protected' => true, 'password' => 'secret']);
    $below = searchFolder('Below', parent: $private);
    searchPhoto($private, 'harbour-private');
    searchPhoto($below, 'harbour-below');
    searchPhoto(searchFolder('Public'), 'harbour-public');

    $picker = searchPicker()
        ->set('search', 'harbour')
        ->assertSee('harbour-public')
        ->assertDontSee(['harbour-private', 'harbour-below']);

    $picker->call('openFolder', $private->id)
        ->fillForm(['password' => 'secret'])
        ->callMountedAction()
        ->assertSet('unlockedFolderIds', [$private->id])
        ->set('search', 'harbour')
        ->assertSee(['harbour-public', 'harbour-private', 'harbour-below']);
});

it('shows a locked folder by name, but not the folders below it', function () {
    $private = searchFolder('Harbour private', ['is_protected' => true, 'password' => 'secret']);
    searchFolder('Harbour below', parent: $private);

    searchPicker()
        ->set('search', 'harbour')
        ->assertSee('Harbour private')
        ->assertDontSee('Harbour below');
});

it('opens a folder from the results with its contents instead of the results', function () {
    $offers = searchFolder('Harbour offers');

    searchPicker()
        ->set('search', 'harbour')
        ->call('openFolder', $offers->id)
        ->assertSet('currentFolderId', $offers->id)
        ->assertSet('search', '');
});

it('opens a folder unlocked in the picker again without its password', function () {
    $private = searchFolder('Private', ['is_protected' => true, 'password' => 'secret']);

    searchPicker()
        ->call('openFolder', $private->id)
        ->fillForm(['password' => 'secret'])
        ->callMountedAction()
        ->call('goBack')
        ->call('openFolder', $private->id)
        ->assertActionNotMounted('verifyPassword')
        ->assertSet('currentFolderId', $private->id);
});

it('does not let the browser unlock folders', function () {
    $private = searchFolder('Private', ['is_protected' => true, 'password' => 'secret']);

    searchPicker()->set('unlockedFolderIds', [$private->id]);
})->throws(CannotUpdateLockedPropertyException::class);

it('searches only the collection of a picker limited to one', function () {
    searchPhoto(searchFolder('Offers'), 'harbour-offer');
    searchPhoto(searchFolder('Logos'), 'harbour-logo');

    searchPicker(['collectionName' => 'logos'])
        ->set('search', 'harbour')
        ->assertSee('harbour-logo')
        ->assertDontSee('harbour-offer');
});
