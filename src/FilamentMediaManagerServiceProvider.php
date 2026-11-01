<?php

namespace TomatoPHP\FilamentMediaManager;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use TomatoPHP\FilamentMediaManager\Console\FilamentMediaManagerInstall;
use TomatoPHP\FilamentMediaManager\Console\StoreMediaDimensions;
use TomatoPHP\FilamentMediaManager\Livewire\FolderComponent;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Services\FilamentMediaManagerServices;
use TomatoPHP\FilamentMediaManager\Services\ImageEditor;
use TomatoPHP\FilamentMediaManager\Services\MediaDimensions;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

class FilamentMediaManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register generate command
        $this->commands([
            FilamentMediaManagerInstall::class,
            StoreMediaDimensions::class,
        ]);

        // Register Config file
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-media-manager.php', 'filament-media-manager');

        // Publish Config
        $this->publishes([
            __DIR__ . '/../config/filament-media-manager.php' => config_path('filament-media-manager.php'),
        ], 'filament-media-manager-config');

        // Register Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filament-media-manager-migrations');
        // Register views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-media-manager');

        // Publish Views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-media-manager'),
        ], 'filament-media-manager-views');

        // Register Langs
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-media-manager');

        // Publish Lang
        $this->publishes([
            __DIR__ . '/../resources/lang' => base_path('lang/vendor/filament-media-manager'),
        ], 'filament-media-manager-lang');

        if (config('filament-media-manager.api.active')) {
            // Register Routes
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        $this->app->bind('filament-media-manager', function () {
            return new FilamentMediaManagerServices;
        });

        Livewire::component('folder-component', FolderComponent::class);
        Livewire::component('media-picker', MediaPicker::class);
    }

    public function boot(): void
    {
        // Spatie removes a medium's files in an observer, which it only registers for its configured media model.
        // Model events carry the class of the model, so the media this package deletes through its own model
        // would leave their files behind.
        $observer = config('media-library.media_observer', MediaObserver::class);

        collect([Media::class, config('filament-media-manager.model.media')])
            ->filter()
            ->unique()
            ->reject(fn (string $model): bool => $model === config('media-library.media_model'))
            ->each(fn (string $model) => $model::observe($this->app->make($observer)));

        // A video's poster and an edited image's original are stored next to it; Spatie only removes its own files
        collect([Media::class, config('filament-media-manager.model.media'), config('media-library.media_model')])
            ->filter()
            ->unique()
            ->each(fn (string $model) => Event::listen("eloquent.deleting: {$model}", function ($media): void {
                VideoPoster::remove($media, save: false);
                ImageEditor::removeOriginal($media);
            }));

        FilamentAsset::register([
            Css::make('cropper', __DIR__ . '/../resources/dist/cropper/cropper.min.css')->loadedOnRequest(),
            Js::make('cropper', __DIR__ . '/../resources/dist/cropper/cropper.min.js')->loadedOnRequest(),
        ], 'tomatophp/filament-media-manager');

        // The dimensions of every image and video added, from the media manager or anywhere else
        Event::listen(MediaHasBeenAddedEvent::class, fn (MediaHasBeenAddedEvent $event) => MediaDimensions::store($event->media));
    }
}
