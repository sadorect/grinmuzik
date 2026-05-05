<?php

namespace App\Http\Controllers;

use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TrackDownloadController extends Controller
{
    public function __invoke(Request $request, Track $track): RedirectResponse|StreamedResponse
    {
        abort_unless($track->is_published, 404);

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($track->canBeDownloadedBy($request->user()), 403);

        $source = $track->getAudioDownloadSource();

        abort_if($source === null, 404);

        $disk = Storage::disk($source['disk']);
        $filename = $track->getDownloadFilename();
        $serveThroughApplication = $request->ajax()
            || $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if (! $serveThroughApplication) {
            try {
                $temporaryUrl = $disk->temporaryUrl(
                    $source['path'],
                    now()->addMinutes((int) config('media-storage.private_url_ttl_minutes', 15)),
                    [
                        'ResponseContentDisposition' => 'attachment; filename="'.$filename.'"',
                        'ResponseCacheControl' => 'private, no-store, no-cache, must-revalidate',
                    ],
                );

                if (filled($temporaryUrl)) {
                    $track->incrementDownloadsCount();

                    return redirect()->away($temporaryUrl);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $track->incrementDownloadsCount();

        return $disk->download(
            $source['path'],
            $filename,
            [
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            ],
        );
    }
}
