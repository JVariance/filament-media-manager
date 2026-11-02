<?php

namespace TomatoPHP\FilamentMediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Tests\Models\Product;
use TomatoPHP\FilamentMediaManager\Tests\Models\User;
use TomatoPHP\FilamentMediaManager\Tests\Support\PickerForm;
use TomatoPHP\FilamentMediaManager\Tests\Support\PickerRecordForm;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());

    $this->photos = Folder::factory()->create(['name' => 'Photos', 'collection' => 'photos']);
    $this->media = $this->photos->addMedia(UploadedFile::fake()->image('beach.jpg'))->toMediaCollection('photos');

    // The media page keeps the open folder in the session, and the media model limits its queries to it
    session()->put('folder_id', Folder::factory()->create(['name' => 'Logos', 'collection' => 'logos'])->id);
});

it('shows the picked media of other folders than the one open on the media page', function () {
    livewire(PickerForm::class, ['initial' => [$this->media->uuid]])
        ->assertSee('beach.jpg');
});

it('loads the attached media of other folders on edit', function () {
    $product = Product::create(['name' => 'Chair']);

    DB::table('media_has_models')->insert([
        'model_type' => Product::class,
        'model_id' => $product->id,
        'media_id' => $this->media->id,
        'order_column' => 0,
    ]);

    livewire(PickerRecordForm::class, ['product' => $product])
        ->assertSet('data.media', [$this->media->uuid]);
});

it('keeps the media of other folders attached on save', function () {
    $product = Product::create(['name' => 'Chair']);

    livewire(PickerRecordForm::class, ['product' => $product])
        ->set('data.media', [$this->media->uuid])
        ->call('save');

    expect(DB::table('media_has_models')->where('model_id', $product->id)->pluck('media_id')->all())
        ->toBe([$this->media->id]);
});
