<?php

namespace TomatoPHP\FilamentMediaManager;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use TomatoPHP\FilamentMediaManager\Http\Controllers\MediaSourceController;
use TomatoPHP\FilamentMediaManager\Livewire\MediaPicker;
use TomatoPHP\FilamentMediaManager\Resources\FolderResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;

class FilamentMediaManagerPlugin implements Plugin
{
    public ?bool $allowSubFolders = false;

    public ?bool $allowUserAccess = false;

    public ?string $navigationLabel = null;

    public ?string $navigationGroup = null;

    public ?string $navigationIcon = null;

    public function getId(): string
    {
        return 'filament-media-manager';
    }

    public function allowSubFolders(bool $condation = true): static
    {
        $this->allowSubFolders = $condation;

        return $this;
    }

    public function allowUserAccess(bool $condation = true): static
    {
        $this->allowUserAccess = $condation;

        return $this;
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            FolderResource::class,
            MediaResource::class,
        ]);

        $this->registerRoutes($panel);
    }

    /**
     * Separate from register(), so that a plugin that registers its own resources can still call it
     */
    public function registerRoutes(Panel $panel): void
    {
        $panel->authenticatedRoutes(function (): void {
            Route::get('media-manager/media/{media}/source', MediaSourceController::class)
                ->whereNumber('media')
                ->name('media-manager.media.source');
        });
    }

    public function boot(Panel $panel): void
    {
        Livewire::component('media-picker', MediaPicker::class);
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
