<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class ResourceController extends Controller
{
    public function guidelines(): JsonResponse
    {
        $settings = SiteSetting::first();

        return response()->json([
            'content' => implode("\n\n", array_filter([
                '# GrinMuzik Upload Guidelines',
                '## Supported Formats',
                'Audio: MP3, WAV, FLAC, OGG, AAC, M4A (max ' . ($settings?->max_audio_size_mb ?? 50) . ' MB)',
                'Cover Art: JPEG, PNG, WebP (max ' . ($settings?->max_image_size_mb ?? 5) . ' MB)',
                '## Content Policy',
                'All uploaded content must be original or licensed. Explicit content must be flagged.',
                'Hate speech, harassment, and copyright infringement are not permitted.',
                '## Metadata Requirements',
                'Each track must have: title, genre, artist name, and cover art before publishing.',
                '## Monetisation',
                SiteSetting::paymentsEnabled()
                    ? 'Paid downloads and donations are enabled on this platform.'
                    : 'Paid downloads are currently disabled on this platform.',
            ])),
        ]);
    }

    public function genres(): JsonResponse
    {
        $genres = Genre::where('is_active', true)
            ->withCount(['tracks as published_track_count' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get()
            ->map(fn (Genre $genre) => [
                'slug'                => $genre->slug,
                'name'                => $genre->name,
                'description'         => $genre->description,
                'published_tracks'    => $genre->published_track_count,
            ]);

        return response()->json(['genres' => $genres]);
    }

    public function contract(): JsonResponse
    {
        return response()->json([
            'content' => implode("\n\n", [
                '# GrinMuzik Artist Agreement',
                '## Rights',
                'By uploading content you confirm you hold the rights or a valid licence for all uploaded material.',
                'GrinMuzik is granted a non-exclusive, royalty-free licence to display, stream, and distribute your content on the platform.',
                '## Revenue',
                'Artists retain 100% of direct donation revenue minus payment processor fees.',
                'Platform-facilitated payout requires a verified Stripe Connect account.',
                '## Takedowns',
                'Artists may remove their content at any time. Takedowns are processed within 24 hours.',
                '## Termination',
                'GrinMuzik reserves the right to remove content or suspend accounts that violate these terms.',
            ]),
        ]);
    }
}
