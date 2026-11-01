<?php

namespace TomatoPHP\FilamentMediaManager\Form;

use Closure;
use Filament\Forms\Components\Field;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

/**
 * The poster image of a video: a frame of the video, taken while it plays or is paused, or an uploaded image.
 *
 * The state is the URL of the current poster, null for none, or a new image as a data URL, which VideoPoster
 * stores with save().
 */
class VideoPosterPicker extends Field
{
    protected string $view = 'filament-media-manager::forms.video-poster-picker';

    protected Media | Closure | null $media = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (VideoPosterPicker $component): void {
            $media = $component->getMedia();

            $component->state($media ? VideoPoster::url($media) : null);
        });

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && str_starts_with($value, 'data:') && ! VideoPoster::decodeDataUrl($value)) {
                $fail(trans('filament-media-manager::messages.media.poster.invalid'));
            }
        });
    }

    public function media(Media | Closure | null $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function getMedia(): ?Media
    {
        return $this->evaluate($this->media);
    }

    /**
     * The video from the panel's origin, so that its frames can be drawn on a canvas
     */
    public function getSourceUrl(): ?string
    {
        $media = $this->getMedia();

        return $media ? filament()->getCurrentOrDefaultPanel()->route('media-manager.media.source', ['media' => $media->getKey()]) : null;
    }

    /**
     * Store the state as the poster of the media: a new image, none, or the one it has
     */
    public static function save(Media $media, ?string $state): void
    {
        if (blank($state)) {
            VideoPoster::remove($media);

            return;
        }

        if (str_starts_with($state, 'data:')) {
            VideoPoster::storeDataUrl($media, $state);
        }
    }
}
