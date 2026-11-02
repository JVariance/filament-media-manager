<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Filament\Facades\Filament;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Livewire\FolderComponent;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource\Pages\ListFolders;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\FolderLocks;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([
        FolderResource::class,
        MediaResource::class,
    ]);

    Filament::setCurrentPanel($this->panel);

    $this->private = Folder::factory()->protected('secret')->create(['collection' => 'private']);
    $this->other = Folder::factory()->protected('other')->create(['collection' => 'other']);
    $this->below = Folder::factory()->create([
        'collection' => 'below',
        'is_protected' => false,
        'model_type' => Folder::class,
        'model_id' => $this->private->id,
    ]);
});

function unlockFolder(Folder $folder, string $password)
{
    return livewire(FolderComponent::class, ['item' => $folder])
        ->mountAction('getFolderAction', ['item' => $folder->id])
        ->fillForm(['password' => $password])
        ->callMountedAction();
}

function openMediaPage(Folder $folder)
{
    return Livewire::withQueryParams(['folder_id' => $folder->id])->test(ListMedia::class);
}

it('opens only the folder whose password was given', function () {
    unlockFolder($this->private, 'secret')->assertRedirect();

    expect(FolderLocks::unlockedInSession())->toBe([$this->private->id]);

    openMediaPage($this->private)->assertSuccessful();
    openMediaPage($this->other)->assertForbidden();
});

it('does not open a folder below a locked folder by its address', function () {
    openMediaPage($this->below)->assertForbidden();
});

it('opens the folders below a folder opened with its password', function () {
    unlockFolder($this->private, 'secret');

    openMediaPage($this->below)->assertSuccessful();
});

it('opens a folder opened with its password again without it, also after the folders list', function () {
    unlockFolder($this->private, 'secret');

    livewire(ListFolders::class)->assertSuccessful();

    livewire(FolderComponent::class, ['item' => $this->private])
        ->mountAction('getFolderAction', ['item' => $this->private->id])
        ->callMountedAction()
        ->assertRedirect(MediaResource::getUrl('index', ['folder_id' => $this->private->id]));
});

it('accepts a hashed folder password', function () {
    $hashed = Folder::factory()->create(['collection' => 'hashed', 'is_protected' => true, 'password' => bcrypt('secret')]);

    unlockFolder($hashed, 'secret')->assertRedirect();

    expect(FolderLocks::unlockedInSession())->toBe([$hashed->id]);
});
