<?php

namespace TomatoPHP\FilamentMediaManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\MediaLibrary\Support\MediaStream;
use Symfony\Component\HttpFoundation\Response;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;

/**
 * Streams the media of a signed URL (MediaDownload): one file as itself, several as a zip file
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(MediaResource::canViewAny(), 403);

        $media = config('media-library.media_model')::query()
            ->withoutGlobalScopes()
            ->whereKey(array_filter(explode(',', (string) $request->query('media'))))
            ->get();

        abort_if($media->isEmpty(), 404);

        if ($media->count() === 1) {
            return $media->first()->toResponse($request);
        }

        // Files with the same name get a suffix in the zip file
        return MediaStream::create($request->query('name', 'media') . '.zip')
            ->addMedia($media)
            ->toResponse($request);
    }
}
