<?php

namespace TomatoPHP\FilamentMediaManager\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TomatoPHP\FilamentMediaManager\Events\MediaFileRenamed;
use TomatoPHP\FilamentMediaManager\Services\WebSafeFileName;

/**
 * Renames the files of media added before web-safe file names (WebSafeFileName). Spatie renames the file and its
 * conversions when the file name of its media model changes. A renamed file has a new URL: references by the
 * medium's id or UUID still work, a URL written somewhere does not, so the command can print redirects for it, and
 * MediaFileRenamed lets an app add its own.
 */
class WebSafeFileNames extends Command
{
    protected $signature = 'filament-media-manager:web-safe-file-names
        {--dry-run : Show the new file names, rename nothing}
        {--redirects= : Print redirects from the old URLs, for "apache" (.htaccess), "nginx" or "laravel" (routes/web.php)}
        {--language= : The language to transliterate in, the app\'s by default}';

    protected $description = 'Rename the files of all media to web-safe file names';

    public function handle(): int
    {
        $format = $this->option('redirects');

        if ($format && ! in_array($format, ['apache', 'nginx', 'laravel'], true)) {
            $this->error('--redirects is "apache", "nginx" or "laravel".');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $renamed = [];

        config('media-library.media_model')::query()->withoutGlobalScopes()->lazyById()->each(function (Media $media) use ($dryRun, &$renamed): void {
            $fileName = WebSafeFileName::make($media->file_name, $this->option('language'));
            $owner = $media->model;

            if ($owner && ($fileName !== $media->file_name)) {
                $fileName = WebSafeFileName::unique($fileName, $owner, $media->collection_name, $media->getKey());
            }

            if ($fileName === $media->file_name) {
                return;
            }

            $oldFileName = $media->file_name;
            $oldUrl = $media->getUrl();

            if ($dryRun) {
                $newUrl = (clone $media)->forceFill(['file_name' => $fileName])->getUrl();
            } else {
                $media->file_name = $fileName;
                $media->save();
                $newUrl = $media->getUrl();

                event(new MediaFileRenamed($media, $oldFileName, $oldUrl, $newUrl));
            }

            $renamed[] = ['id' => $media->getKey(), 'old' => $oldFileName, 'new' => $fileName, 'oldUrl' => $oldUrl, 'newUrl' => $newUrl];
        });

        if (! $renamed) {
            $this->info('All file names are web-safe already.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'File name', 'Web-safe file name'], array_map(fn (array $file): array => [$file['id'], $file['old'], $file['new']], $renamed));

        $this->info(($dryRun ? 'Would rename ' : 'Renamed ') . count($renamed) . ' file(s).');

        if ($format) {
            $this->newLine();

            foreach ($renamed as $file) {
                $this->line(static::redirect($format, static::path($file['oldUrl']), static::path($file['newUrl'])));
            }
        }

        return self::SUCCESS;
    }

    /**
     * The path of a URL as the web server compares it: decoded
     */
    protected static function path(string $url): string
    {
        return rawurldecode((string) parse_url($url, PHP_URL_PATH));
    }

    protected static function redirect(string $format, string $from, string $to): string
    {
        if ($format === 'laravel') {
            // A missing file is passed on to Laravel, and its storage route is registered after the app's routes
            return 'Route::permanentRedirect(' . var_export($from, true) . ', ' . var_export($to, true) . ');';
        }

        $from = str_replace('"', '\\"', $from);

        return $format === 'apache'
            ? "Redirect 301 \"{$from}\" \"{$to}\""
            : "location = \"{$from}\" { return 301 \"{$to}\"; }";
    }
}
