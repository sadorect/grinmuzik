<?php

use App\Http\Controllers\DonationController;
use App\Http\Controllers\TrackDownloadController;
use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Playlist;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Support\ThemePalette;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

Volt::route('/', 'pages.home')->name('home');

Route::get('/pwa-icon.svg', function () {
    $installAppName = 'GrinMuzik';

    try {
        $siteSettings = Schema::hasTable('site_settings')
            ? SiteSetting::current()
            : null;
    } catch (\Throwable) {
        $siteSettings = null;
    }

    if ($siteSettings?->favicon_url) {
        return redirect()->to($siteSettings->favicon_url);
    }

    if ($siteSettings?->site_logo_url) {
        return redirect()->to($siteSettings->site_logo_url);
    }

    $siteName = $installAppName;
    $themeColor = $siteSettings?->effective_primary_color ?? ThemePalette::DEFAULT_PRIMARY;
    $accentColor = $siteSettings?->effective_secondary_color ?? ThemePalette::DEFAULT_SECONDARY;
    $initials = collect(preg_split('/(?=[A-Z])|[^A-Za-z0-9]+/', $siteName))
        ->filter()
        ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    if ($initials === '') {
        $initials = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $siteName) ?: 'GM', 0, 2));
    }

    $fontSize = Str::length($initials) > 1 ? 172 : 212;
    $siteName = e($siteName);
    $initials = e($initials);

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-labelledby="title">
  <title id="title">{$siteName} app icon</title>
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$themeColor}" />
      <stop offset="100%" stop-color="{$accentColor}" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="120" fill="url(#bg)" />
  <circle cx="400" cy="112" r="48" fill="#FFFFFF" opacity="0.14" />
  <text x="256" y="308" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="700" fill="#FFFFFF">{$initials}</text>
</svg>
SVG;

    return response($svg, 200, [
        'Content-Type' => 'image/svg+xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('pwa.icon');

Route::get('/manifest.webmanifest', function () {
    $installAppName = 'GrinMuzik';

    try {
        $siteSettings = Schema::hasTable('site_settings')
            ? SiteSetting::current()
            : null;
    } catch (\Throwable) {
        $siteSettings = null;
    }

    $siteName = $installAppName;
    $themeColor = $siteSettings?->effective_primary_color ?? ThemePalette::DEFAULT_PRIMARY;
    $installIconUrl = $siteSettings?->favicon_url
        ?? $siteSettings?->site_logo_url
        ?? route('pwa.icon');
    $manifest = [
        'id' => route('home'),
        'name' => $siteName,
        'short_name' => $siteName,
        'description' => 'Discover independent music, follow artists, and keep GrinMuzik on your desktop or home screen.',
        'start_url' => route('home'),
        'scope' => url('/'),
        'display' => 'standalone',
        'background_color' => '#F8FAFC',
        'theme_color' => $themeColor,
        'icons' => [
            [
                'src' => $installIconUrl,
                'sizes' => 'any',
                'purpose' => 'any',
            ],
        ],
    ];

    return response(
        json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        200,
        [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ],
    );
})->name('manifest');

Route::view('/offline', 'offline')->name('offline');

Route::get('/robots.txt', function () {
    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /musicdirector',
        'Disallow: /dashboard',
        'Disallow: /me/',
        'Disallow: /artist/tracks',
        'Disallow: /artist/albums',
        'Disallow: /artist/setup',
        'Sitemap: ' . route('sitemap', absolute: true),
        '',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $staticPages = collect([
        ['loc' => route('home'), 'lastmod' => now()],
        ['loc' => route('browse'), 'lastmod' => now()],
        ['loc' => route('search'), 'lastmod' => now()],
        ['loc' => route('charts'), 'lastmod' => now()],
        ['loc' => route('new-releases'), 'lastmod' => now()],
        ['loc' => route('playlists.public'), 'lastmod' => now()],
        ['loc' => route('about'), 'lastmod' => now()],
        ['loc' => route('artist-guide'), 'lastmod' => now()],
        ['loc' => route('pricing'), 'lastmod' => now()],
        ['loc' => route('privacy'), 'lastmod' => now()],
        ['loc' => route('terms'), 'lastmod' => now()],
        ['loc' => route('contact'), 'lastmod' => now()],
    ]);

    $tracks = Track::query()
        ->published()
        ->select(['slug', 'updated_at'])
        ->latest('updated_at')
        ->get()
        ->map(fn (Track $track) => [
            'loc' => route('track.show', $track->slug),
            'lastmod' => $track->updated_at,
        ]);

    $albums = Album::query()
        ->publiclyVisible()
        ->select(['slug', 'updated_at'])
        ->latest('updated_at')
        ->get()
        ->map(fn (Album $album) => [
            'loc' => route('album.show', $album->slug),
            'lastmod' => $album->updated_at,
        ]);

    $playlists = Playlist::query()
        ->where('is_public', true)
        ->select(['slug', 'updated_at'])
        ->latest('updated_at')
        ->get()
        ->map(fn (Playlist $playlist) => [
            'loc' => route('playlist.show', $playlist->slug),
            'lastmod' => $playlist->updated_at,
        ]);

    $artists = ArtistProfile::query()
        ->publiclyDiscoverable()
        ->select(['slug', 'updated_at'])
        ->latest('updated_at')
        ->get()
        ->map(fn (ArtistProfile $artist) => [
            'loc' => route('artist.page', $artist->slug),
            'lastmod' => $artist->updated_at,
        ]);

    return response()
        ->view('sitemap', [
            'urls' => $staticPages
                ->concat($tracks)
                ->concat($albums)
                ->concat($playlists)
                ->concat($artists),
        ])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

// ── Public browse & search ────────────────────────────────────────────────
Volt::route('/browse', 'pages.browse')->name('browse');
Volt::route('/search', 'pages.search')->name('search');
Volt::route('/charts', 'pages.charts')->name('charts');
Volt::route('/new-releases', 'pages.new-releases')->name('new-releases');
Volt::route('/playlists', 'pages.public-playlists')->name('playlists.public');
Volt::route('/album/{album:slug}', 'pages.album-show')->name('album.show');
Volt::route('/track/{track:slug}', 'pages.track-show')->name('track.show');
Volt::route('/playlist/{playlist:slug}', 'pages.playlist-show')->name('playlist.show');

// ── Static / info pages ───────────────────────────────────────────────────
Volt::route('/about', 'pages.about')->name('about');
Volt::route('/artist-guide', 'pages.artist-guide')->name('artist-guide');
Volt::route('/pricing', 'pages.pricing')->name('pricing');
Volt::route('/privacy', 'pages.privacy')->name('privacy');
Volt::route('/terms', 'pages.terms')->name('terms');
Volt::route('/contact', 'pages.contact')->name('contact');

// ── Donation / unlock ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/stripe/intent/{track}', [DonationController::class, 'createIntent'])->middleware('throttle:20,1')->name('donation.intent');
    Route::get('/track/{track:slug}/download', TrackDownloadController::class)->name('track.download');
});
Route::post('/stripe/webhook', [DonationController::class, 'webhook'])->name('donation.webhook');

// Shared authenticated dashboard — redirects by role
Route::get('dashboard', function () {
    $user = auth()->user();
    if ($user->isArtist()) {
        return redirect()->route('artist.dashboard');
    }
    return redirect()->route('listener.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// ── Artist area ────────────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('artist')->name('artist.')->group(function () {
    Volt::route('/setup', 'pages.artist.setup')->name('setup');
    Volt::route('/', 'pages.artist.dashboard')->name('dashboard');
    Volt::route('/profile', 'pages.artist.edit-profile')->name('edit-profile');
    Volt::route('/tracks', 'pages.artist.tracks')->name('tracks');
    Volt::route('/tracks/upload', 'pages.artist.upload-track')->name('upload-track');
    Volt::route('/tracks/{track}/edit', 'pages.artist.edit-track')->name('edit-track');
    Volt::route('/albums', 'pages.artist.albums')->name('albums');
    Volt::route('/albums/create', 'pages.artist.create-album')->name('create-album');
    Volt::route('/albums/{album}/edit', 'pages.artist.edit-album')->name('edit-album');
});

// ── Public artist profile — registered AFTER the /artist/* group so static paths win
Volt::route('/artist/{profile:slug}', 'pages.artist-page')->name('artist.page');

Volt::route('/my-playlists', 'pages.listener.playlists')->middleware(['auth'])->name('playlists.mine');

// ── Listener area ──────────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('me')->name('listener.')->group(function () {
    Volt::route('/', 'pages.listener.dashboard')->name('dashboard');
    Volt::route('/playlists', 'pages.listener.playlists')->name('playlists');
    Volt::route('/liked', 'pages.listener.liked')->name('liked');
    Volt::route('/history', 'pages.listener.history')->name('history');
});

// ── Inbox (all authenticated users) ───────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Volt::route('/inbox', 'pages.inbox')->name('inbox');
});

Route::post('logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('home');
})->name('logout');
// Admin panel is served by Filament at /musicdirector
require __DIR__.'/auth.php';
