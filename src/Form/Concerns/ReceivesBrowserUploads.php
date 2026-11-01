<?php

namespace TomatoPHP\FilamentMediaManager\Form\Concerns;

use Filament\Forms\Components\Concerns\HasFileAttachments;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * An image made in the browser (on a canvas) and uploaded as a file, since a request may not be larger than
 * Livewire's payload limit. Filament accepts uploads from the browser only for file upload fields and fields with
 * file attachments, so the image goes to componentFileAttachments like a rich editor's, and the state says
 * UPLOADED; the form's data then holds the file.
 */
trait ReceivesBrowserUploads
{
    use HasFileAttachments;

    public const UPLOADED = 'uploaded';

    protected function setUpBrowserUploads(): void
    {
        $this->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->dehydrateStateUsing(fn (self $component, mixed $state): mixed => $component->getUpload($state) ?: $state);
    }

    /**
     * The image the browser uploaded for the state: false when it is not one of the accepted images, null when the
     * state holds no new image
     */
    public function getUpload(mixed $state): TemporaryUploadedFile | false | null
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if ($state !== static::UPLOADED) {
            return null;
        }

        return $this->getUploadedFileAttachment() ?? false;
    }
}
