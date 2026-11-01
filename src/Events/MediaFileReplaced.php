<?php

namespace TomatoPHP\FilamentMediaManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The file of a medium was replaced, e.g. by an edited image; listen to it to renew what was made from the file
 */
class MediaFileReplaced
{
    use Dispatchable;

    public function __construct(public Media $media) {}
}
