<?php

namespace App\Http\Controllers;

use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackDownloadController extends Controller
{
    public function __invoke(Request $request, Track $track): StreamedResponse
    {
        abort_unless($track->is_published, 404);
        abort_unless($track->canBeDownloadedBy($request->user()), 403);

        $source = $track->getAudioDownloadSource();

        abort_if($source === null, 404);

        $track->incrementDownloadsCount();

        return Storage::disk($source['disk'])->download(
            $source['path'],
            $track->getDownloadFilename(),
            [
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            ],
        );
    }
}
