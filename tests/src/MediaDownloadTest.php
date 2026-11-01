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
use TomatoPHP\FilamentMediaManager\Services\MediaDownload;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;
use ZipArchive;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->panel->resources([MediaResource::class]);

    Storage::fake('public');

    $this->folder = Folder::factory()->create(['name' => 'Summer Photos']);
});

function downloadable(Folder $folder, string $name): Media
{
    $media = $folder->addMedia(UploadedFile::fake()->createWithContent($name, "contents of {$name}"))->toMediaCollection($folder->collection);

    return Media::query()->withoutGlobalScopes()->findOrFail($media->getKey());
}

/**
 * @return array<string, string> contents by name
 */
function zipContents(string $zip): array
{
    $file = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($file, $zip);

    $archive = new ZipArchive;
    $archive->open($file);

    $contents = [];

    for ($i = 0; $i < $archive->numFiles; $i++) {
        $contents[$archive->getNameIndex($i)] = $archive->getFromIndex($i);
    }

    $archive->close();
    unlink($file);

    return $contents;
}

it('downloads the file of the details', function () {
    $media = downloadable($this->folder, 'report.pdf');

    $page = Livewire::withQueryParams(['folder_id' => $this->folder->id])
        ->test(ListMedia::class)
        ->callAction('downloadMedia', arguments: ['record' => ['id' => $media->id]])
        ->assertRedirectContains('media-manager/media/download');

    $response = get($page->effects['redirect'])->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('report.pdf')
        ->and($response->streamedContent())->toBe('contents of report.pdf');
});

it('downloads one selected file as itself and several as a zip file named after the folder', function () {
    $first = downloadable($this->folder, 'photo.jpg');
    $second = downloadable($this->folder, 'notes.txt');
    $page = Livewire::withQueryParams(['folder_id' => $this->folder->id])->test(ListMedia::class);

    $page->selectTableRecords([$first->id])
        ->callAction(TestAction::make('download')->table()->bulk());

    expect(get($page->effects['redirect'])->streamedContent())->toBe('contents of photo.jpg');

    $page = Livewire::withQueryParams(['folder_id' => $this->folder->id])->test(ListMedia::class)
        ->selectTableRecords([$first->id, $second->id])
        ->callAction(TestAction::make('download')->table()->bulk());

    $response = get($page->effects['redirect'])->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('summer-photos.zip')
        ->and(zipContents($response->streamedContent()))->toBe([
            'photo.jpg' => 'contents of photo.jpg',
            'notes.txt' => 'contents of notes.txt',
        ]);
});

it('keeps files with the same name apart in the zip file', function () {
    $first = downloadable($this->folder, 'photo.jpg');
    $second = downloadable(Folder::factory()->create(), 'photo.jpg');

    $contents = zipContents(get(MediaDownload::url([$first, $second]))->streamedContent());

    expect($contents)->toHaveCount(2)
        ->and(array_values($contents))->toBe(['contents of photo.jpg', 'contents of photo.jpg']);
});

it('serves only the media it was signed for, for a few minutes, to users of the panel', function () {
    $media = downloadable($this->folder, 'photo.jpg');
    $other = downloadable($this->folder, 'secret.txt');
    $url = MediaDownload::url([$media]);

    get(str_replace("media={$media->id}", "media={$other->id}", $url))->assertForbidden();
    get(preg_replace('/&signature=.*/', '', $url))->assertForbidden();

    $this->travel(11)->minutes();
    get($url)->assertForbidden();
    $this->travelBack();

    auth()->logout();
    get(MediaDownload::url([$media]))->assertRedirect();
});
