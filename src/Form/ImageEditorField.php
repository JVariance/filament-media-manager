<?php

namespace TomatoPHP\FilamentMediaManager\Form;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Facades\FilamentAsset;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Form\Concerns\ReceivesBrowserUploads;

/**
 * Crop, rotate, straighten, flip and zoom an image with Cropper.js. The browser draws the result on a canvas in the
 * image's type and uploads it (ReceivesBrowserUploads); its buttons then call the mounted action with the argument
 * "mode": "replace" or "copy".
 */
class ImageEditorField extends Field
{
    use ReceivesBrowserUploads;

    protected string $view = 'filament-media-manager::forms.image-editor';

    protected Media | Closure | null $media = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBrowserUploads();

        $this->rule(fn (ImageEditorField $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $file = $component->getUpload($value);

            if (($file === false) || ($file && ! @getimagesizefromstring((string) $file->get()))) {
                $fail(trans('filament-media-manager::messages.media.editor.invalid'));
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
     * The image from the panel's origin, so that the edited image can be read from the canvas
     */
    public function getSourceUrl(): ?string
    {
        $media = $this->getMedia();

        return $media ? filament()->getCurrentOrDefaultPanel()->route('media-manager.media.source', ['media' => $media->getKey(), 'v' => $media->updated_at?->timestamp]) : null;
    }

    /**
     * @return array<string, float | string | null> label by ratio: null for a free crop, "original" for the image's
     */
    public function getAspectRatios(): array
    {
        return [
            trans('filament-media-manager::messages.media.editor.free') => null,
            trans('filament-media-manager::messages.media.editor.original') => 'original',
            '1:1' => 1,
            '4:3' => 4 / 3,
            '3:2' => 3 / 2,
            '16:9' => 16 / 9,
            '3:4' => 3 / 4,
            '9:16' => 9 / 16,
        ];
    }

    public function getCropperScriptSrc(): string
    {
        return FilamentAsset::getScriptSrc('cropper', 'tomatophp/filament-media-manager');
    }

    public function getCropperStyleHref(): string
    {
        return FilamentAsset::getStyleHref('cropper', 'tomatophp/filament-media-manager');
    }
}
