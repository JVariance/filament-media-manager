<?php

namespace TomatoPHP\FilamentMediaManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

/**
 * A video from the origin of the panel. The browser can only draw a frame of a video on a canvas (to take it as the
 * poster) when the video comes from the page's own origin, and the files are often served from another one, e.g.
 * the app's domain while the panel has its own.
 */
class MediaSourceController extends Controller
{
    public function __invoke(int $media): Response
    {
        $media = Media::query()->withoutGlobalScopes()->findOrFail($media);

        abort_unless(VideoPoster::isVideo($media) && MediaResource::canViewAny(), 404);

        $path = $media->getPath();

        // A file response answers range requests, which the browser needs to seek in the video
        if (is_file($path)) {
            return response()->file($path, ['Content-Type' => $media->mime_type]);
        }

        return redirect()->away($media->getUrl());
    }
}
