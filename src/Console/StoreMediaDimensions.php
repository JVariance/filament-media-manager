<?php

namespace TomatoPHP\FilamentMediaManager\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Services\MediaDimensions;

/**
 * Measures the images and videos added before the dimensions were stored on upload
 */
class StoreMediaDimensions extends Command
{
    protected $signature = 'filament-media-manager:dimensions {--force : Measure media that have dimensions already, too}';

    protected $description = 'Store the width and height of all images and videos (and the duration of videos)';

    public function handle(): int
    {
        $stored = 0;
        $query = Media::query()->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')->orWhere('mime_type', 'like', 'video/%'));
        $bar = $this->output->createProgressBar($query->count());

        $query->lazyById()->each(function (Media $media) use (&$stored, $bar): void {
            if (MediaDimensions::store($media, (bool) $this->option('force'))) {
                $stored++;
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Stored the dimensions of {$stored} media.");

        return self::SUCCESS;
    }
}
