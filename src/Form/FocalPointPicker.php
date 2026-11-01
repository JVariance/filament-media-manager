<?php

namespace TomatoPHP\FilamentMediaManager\Form;

use Closure;
use Filament\Forms\Components\Field;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

/**
 * Click on the image or video to set its focal point. The state is ['x' => 0–100, 'y' => 0–100] in percent, or
 * null for the center. A video shows its poster image, or its first frame without one.
 */
class FocalPointPicker extends Field
{
    protected string $view = 'filament-media-manager::forms.focal-point-picker';

    protected Media | Closure | null $media = null;

    public function media(Media | Closure | null $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function getMedia(): ?Media
    {
        return $this->evaluate($this->media);
    }

    public function isVideo(): bool
    {
        return VideoPoster::isVideo($this->getMedia());
    }

    /**
     * The picture to click on: the image, or the poster of a video
     */
    public function getImageUrl(): ?string
    {
        $media = $this->getMedia();

        if (! $media) {
            return null;
        }

        return $this->isVideo() ? VideoPoster::url($media) : $media->getUrl();
    }

    public function getVideoUrl(): ?string
    {
        // From the start, Safari shows no frame of a video that has not played
        return $this->isVideo() ? $this->getMedia()->getUrl() . '#t=0.1' : null;
    }
}
