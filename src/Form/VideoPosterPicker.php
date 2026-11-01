<?php

namespace TomatoPHP\FilamentMediaManager\Form;

use Closure;
use Filament\Forms\Components\Field;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Form\Concerns\ReceivesBrowserUploads;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

/**
 * The poster image of a video: a frame of the video, taken while it plays or is paused, or an uploaded image.
 *
 * The state is the URL of the current poster, null for none, or UPLOADED once the browser has uploaded a new image
 * (as a file, since a request may not be larger than Livewire's payload limit); the form's data then holds the
 * uploaded file, which save() stores.
 */
class VideoPosterPicker extends Field
{
    use ReceivesBrowserUploads;

    protected string $view = 'filament-media-manager::forms.video-poster-picker';

    protected Media | Closure | null $media = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (VideoPosterPicker $component): void {
            $media = $component->getMedia();

            $component->state($media ? VideoPoster::url($media) : null);
        });

        $this->setUpBrowserUploads();

        $this->rule(fn (VideoPosterPicker $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $file = $component->getUpload($value);

            if (($file === false) || ($file && ! VideoPoster::isImage((string) $file->get()))) {
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
    public static function save(Media $media, mixed $state): void
    {
        if (blank($state)) {
            VideoPoster::remove($media);

            return;
        }

        if ($state instanceof TemporaryUploadedFile) {
            VideoPoster::store($media, (string) $state->get());
            $state->delete();
        }
    }
}
