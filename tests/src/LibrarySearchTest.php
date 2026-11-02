<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource\Pages\ListFolders;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\FolderLocks;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());

    $this->panel->resources([
        FolderResource::class,
        MediaResource::class,
    ]);

    Filament::setCurrentPanel($this->panel);
});

function libraryFolder(string $name, array $attributes = [], ?Folder $parent = null): Folder
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

function libraryPhoto(Folder $folder, string $name): Media
{
    // A sub folder keeps its media on its parent, like an upload into it does
    $model = $folder->model_type === Folder::class ? Folder::find($folder->model_id) : $folder;
    $media = $model->addMedia(UploadedFile::fake()->image("{$name}.jpg"))->toMediaCollection($folder->collection);

    return Media::withoutGlobalScope('folder')->find($media->id);
}

function libraryPage(?Folder $folder = null, ?string $search = null)
{
    return Livewire::withQueryParams(array_filter(['folder_id' => $folder?->id, 'search' => $search]))->test(ListMedia::class);
}

it('searches the media of all folders from inside a folder, and names the path of their folder', function () {
    $offers = libraryFolder('Offers');
    $students = libraryFolder('Students', parent: $offers);
    $other = libraryFolder('Other');

    $front = libraryPhoto($offers, 'harbour-front');
    $room = libraryPhoto($students, 'harbour-room');
    $garden = libraryPhoto($other, 'garden');

    libraryPage($other)
        ->set('tableSearch', 'harbour')
        ->assertCanSeeTableRecords([$front, $room])
        ->assertCanNotSeeTableRecords([$garden])
        ->assertSee(['Search results in all folders', 'Offers › Students']);
});

it('shows the search of the folders list on the media page, without a folder', function () {
    $front = libraryPhoto(libraryFolder('Offers'), 'harbour-front');

    libraryPage(search: 'harbour')
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$front])
        ->assertSee('Search results in all folders');
});

it('sends a search on the folders list to the media page', function () {
    livewire(ListFolders::class)
        ->set('tableSearch', 'harbour')
        ->assertRedirect(MediaResource::getUrl('index', ['search' => 'harbour']));
});

it('goes back to the folders list when the search without a folder is cleared', function () {
    libraryPage(search: 'harbour')
        ->set('tableSearch', '')
        ->assertRedirect(FolderResource::getUrl('index'));
});

it('leaves out the media of a locked folder and of the folders below it until it is opened with its password', function () {
    $private = libraryFolder('Private', ['is_protected' => true, 'password' => 'secret']);
    $hidden = libraryPhoto($private, 'harbour-private');
    $below = libraryPhoto(libraryFolder('Below', parent: $private), 'harbour-below');
    $public = libraryPhoto(libraryFolder('Public'), 'harbour-public');

    libraryPage(search: 'harbour')
        ->assertCanSeeTableRecords([$public])
        ->assertCanNotSeeTableRecords([$hidden, $below]);

    FolderLocks::unlockInSession($private);

    libraryPage(search: 'harbour')
        ->assertCanSeeTableRecords([$public, $hidden, $below]);
});

it('finds folders of all levels by name, a locked one but not the folders below it', function () {
    $offers = libraryFolder('Offers');
    libraryFolder('Harbour flats', parent: libraryFolder('Rentals', parent: $offers));
    $private = libraryFolder('Harbour private', ['is_protected' => true, 'password' => 'secret']);
    libraryFolder('Harbour below', parent: $private);

    $page = libraryPage(search: 'harbour')
        ->assertSee(['Harbour flats', 'Offers › Rentals', 'Harbour private']);

    expect($page->instance()->getSearchedFolders()->pluck('name')->all())
        ->toEqualCanonicalizing(['Harbour flats', 'Harbour private']);
});

it('does not offer to move or delete the media of a search, which are from several folders', function () {
    $offers = libraryFolder('Offers');
    libraryPhoto($offers, 'harbour-front');

    libraryPage($offers)
        ->assertActionVisible(TestAction::make('moveMedia')->table()->bulk())
        ->set('tableSearch', 'harbour')
        ->assertActionHidden(TestAction::make('moveMedia')->table()->bulk())
        ->assertActionHidden(TestAction::make('delete')->table()->bulk());
});
