<?php

namespace TomatoPHP\FilamentMediaManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Services\ImageEditor;
use TomatoPHP\FilamentMediaManager\Services\VideoPoster;

/**
 * A video or image from the origin of the panel. The browser can only read what it drew on a canvas (a frame of a
 * video taken as its poster, an image edited in the image editor) when the video or image comes from the page's own
 * origin, and the files are often served from another one, e.g. the app's domain while the panel has its own.
 */
class MediaSourceController extends Controller
{
    public function __invoke(int $media): Response
    {
        $media = Media::query()->withoutGlobalScopes()->findOrFail($media);

        abort_unless((VideoPoster::isVideo($media) || ImageEditor::supports($media)) && MediaResource::canViewAny(), 404);

        $path = $media->getPath();

        // A file response answers range requests, which the browser needs to seek in a video
        if (is_file($path)) {
            return response()->file($path, ['Content-Type' => $media->mime_type]);
        }

        return redirect()->away($media->getUrl());
    }
}
