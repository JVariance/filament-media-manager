<?php

namespace TomatoPHP\FilamentMediaManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The file of a medium got another name; listen to it to send its old URL to the new one
 */
class MediaFileRenamed
{
    use Dispatchable;

    public function __construct(
        public Media $media,
        public string $oldFileName,
        public string $oldUrl,
        public string $newUrl,
    ) {}
}
