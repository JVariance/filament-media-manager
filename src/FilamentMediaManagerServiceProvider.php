<?php

namespace TomatoPHP\FilamentMediaManager;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Console\FilamentMediaManagerInstall;
use TomatoPHP\FilamentMediaManager\Livewire\FolderComponent;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Services\FilamentMediaManagerServices;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

class FilamentMediaManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register generate command
        $this->commands([
            FilamentMediaManagerInstall::class,
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
        // A video's poster is stored next to it; Spatie only removes the video's own files
        collect([Media::class, config('filament-media-manager.model.media'), config('media-library.media_model')])
            ->filter()
            ->unique()
            ->each(fn (string $model) => Event::listen("eloquent.deleting: {$model}", fn ($media) => VideoPoster::remove($media, save: false)));
    }
}
