<?php

namespace App\Providers;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Playlist;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\User;
use App\Contracts\AiNewsletterProvider;
use App\Services\Ai\AnthropicNewsletterProvider;
use App\Services\Ai\OllamaNewsletterProvider;
use App\Services\Ai\OpenAiNewsletterProvider;
use App\Observers\AlbumObserver;
use App\Observers\ArtistProfileObserver;
use App\Observers\TrackObserver;
use App\Support\AuditLogger;
use App\Support\ThemePalette;
use App\Support\ArtistActivityNotifier;
use App\Support\MediaLibrary\OrganizedPathGenerator;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config([
            'media-library.disk_name' => config('media-storage.public_disk'),
            'media-library.path_generator' => OrganizedPathGenerator::class,
        ]);

        $this->app->bind(AiNewsletterProvider::class, function () {
            return match (config('services.ai.provider')) {
                'openai' => new OpenAiNewsletterProvider(),
                'ollama' => new OllamaNewsletterProvider(),
                default => new AnthropicNewsletterProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRequestTrust();

        /*
         * Deliberately NO morph map for content_reports.
         *
         * A map would be tidier, but Relation::morphMap is global: aliasing
         * Track or ArtistProfile also changes what Spatie Media Library writes
         * to media.model_type, and every existing media row stores the
         * fully-qualified name. Adding the map would orphan the artwork and
         * audio of every track already uploaded.
         *
         * enforceMorphMap is worse still — it makes any unmapped model throw,
         * which takes out media, notifications and every other polymorphic
         * relation in the app.
         *
         * The report form's safety does not depend on aliasing anyway:
         * ReportContent::resolveContent() refuses any class that does not use
         * the Reportable trait, so a tampered payload has nothing to aim at.
         */

        $this->bootAuditLog();

        Vite::usePreloadTagAttributes(fn (string $src, string $url) => str_ends_with(strtok($url, '?') ?: $url, '.css') ? false : []);

        \Illuminate\Support\Facades\Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            app(ArtistActivityNotifier::class)->sendPasswordChanged($event->user, 'reset');
        });

        \Illuminate\Support\Facades\Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user instanceof User) {
                $event->user->refresh()->syncArtistVerificationState();
            }
        });

        View::composer('*', function ($view): void {
            static $sharedCache = [];

            $routeName = request()->route()?->getName();
            $cacheKey = implode('|', [
                request()->method(),
                request()->fullUrl(),
                (string) ($routeName ?? ''),
                (string) (auth()->id() ?? 'guest'),
            ]);

            if (! array_key_exists($cacheKey, $sharedCache)) {
                try {
                    $siteSettings = Schema::hasTable('site_settings')
                        ? SiteSetting::current()
                        : null;
                } catch (Throwable) {
                    $siteSettings = null;
                }

                $siteName = $siteSettings?->effective_site_name ?? 'GrinMuzik';
                $siteTitle = $siteSettings?->effective_site_title ?? $siteName;
                $siteUrl = url('/');
                $siteLogo = $siteSettings?->site_logo_url;
                $themeCssVariables = $siteSettings?->theme_css_variables ?? ThemePalette::cssVariables();
                $themePrimaryColor = $siteSettings?->effective_primary_color ?? ThemePalette::DEFAULT_PRIMARY;
                $showPublicThemeToggle = $siteSettings?->effective_show_public_theme_toggle ?? true;
                $showRegistrationRoleGuide = $siteSettings?->effective_show_registration_role_guide ?? true;
                $paymentsEnabled = $siteSettings?->effective_payments_enabled ?? ! SiteSetting::supportsMonetizationControls();

                $defaultDescription = 'Discover independent music, explore fresh releases, support artists, and build playlists on GrinMuzik.';

                $routeSeo = [
                    'home' => [
                        'title' => 'Independent Music Streaming',
                        'description' => 'Stream independent music, discover featured artists, browse new releases, and support creators directly on GrinMuzik.',
                        'canonical' => route('home'),
                    ],
                    'browse' => [
                        'title' => 'Browse Music',
                        'description' => 'Browse independent tracks by genre, mood, and popularity on GrinMuzik.',
                        'canonical' => route('browse'),
                    ],
                    'charts' => [
                        'title' => 'Music Charts',
                        'description' => 'See the top-performing tracks, plays, likes, and downloads across GrinMuzik charts.',
                        'canonical' => route('charts'),
                    ],
                    'new-releases' => [
                        'title' => 'New Releases',
                        'description' => 'Catch the latest songs and fresh drops from independent artists on GrinMuzik.',
                        'canonical' => route('new-releases'),
                    ],
                    'playlists.public' => [
                        'title' => 'Public Playlists',
                        'description' => 'Explore public playlists curated by listeners and discover new music collections on GrinMuzik.',
                        'canonical' => route('playlists.public'),
                    ],
                    'about' => [
                        'title' => 'About',
                        'description' => 'Learn how GrinMuzik helps independent artists share music and connect with listeners.',
                        'canonical' => route('about'),
                    ],
                    'artist-guide' => [
                        'title' => 'Artist Guide',
                        'description' => 'Read the GrinMuzik artist guide for publishing music, growing your audience, and earning support.',
                        'canonical' => route('artist-guide'),
                    ],
                    'pricing' => [
                        'title' => 'Creator Access',
                        'description' => 'See how GrinMuzik helps artists set up profiles, upload music, and build their presence.',
                        'canonical' => route('pricing'),
                    ],
                    'privacy' => [
                        'title' => 'Privacy Policy',
                        'description' => 'Read the GrinMuzik privacy policy.',
                        'canonical' => route('privacy'),
                    ],
                    'terms' => [
                        'title' => 'Terms of Service',
                        'description' => 'Review the GrinMuzik terms of service.',
                        'canonical' => route('terms'),
                    ],
                    'contact' => [
                        'title' => 'Contact',
                        'description' => 'Get in touch with the GrinMuzik team.',
                        'canonical' => route('contact'),
                    ],
                    'search' => [
                        'title' => 'Search',
                        'description' => 'Search GrinMuzik for tracks, artists, albums, and playlists.',
                        'canonical' => route('search'),
                        'robots' => 'noindex,follow',
                    ],
                    'login' => [
                        'title' => 'Sign In',
                        'robots' => 'noindex,nofollow',
                    ],
                    'register' => [
                        'title' => 'Create Account',
                        'robots' => 'noindex,nofollow',
                    ],
                    'profile' => [
                        'title' => 'Profile',
                        'robots' => 'noindex,nofollow',
                    ],
                    'dashboard' => [
                        'title' => 'Dashboard',
                        'robots' => 'noindex,nofollow',
                    ],
                    'listener.dashboard' => [
                        'title' => 'My Music',
                        'robots' => 'noindex,nofollow',
                    ],
                    'listener.playlists' => [
                        'title' => 'My Playlists',
                        'robots' => 'noindex,nofollow',
                    ],
                    'listener.liked' => [
                        'title' => 'Liked Tracks',
                        'robots' => 'noindex,nofollow',
                    ],
                    'listener.history' => [
                        'title' => 'Play History',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.dashboard' => [
                        'title' => 'Artist Dashboard',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.edit-profile' => [
                        'title' => 'Edit Artist Profile',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.tracks' => [
                        'title' => 'Artist Tracks',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.upload-track' => [
                        'title' => 'Upload Track',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.edit-track' => [
                        'title' => 'Edit Track',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.albums' => [
                        'title' => 'Artist Albums',
                        'robots' => 'noindex,nofollow',
                    ],
                    'artist.create-album' => [
                        'title' => 'Create Album',
                        'robots' => 'noindex,nofollow',
                    ],
                ];

                $organizationSchema = array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => $siteName,
                    'url' => $siteUrl,
                    'logo' => $siteLogo,
                    'sameAs' => collect($siteSettings?->social_links ?? [])
                        ->pluck('url')
                        ->filter()
                        ->values()
                        ->all(),
                ]);

                $websiteSchema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $siteName,
                    'url' => $siteUrl,
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => route('search') . '?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ];

                $activeRouteSeo = $routeSeo[$routeName] ?? [];

                if ($routeName === 'artist.page') {
                    $profile = request()->route('profile');

                    if ($profile instanceof ArtistProfile) {
                        $profile->loadMissing('user', 'genres');

                        $description = $profile->bio
                            ?: 'Explore music, albums, and artist updates from ' . ($profile->stage_name ?? $profile->user?->name ?? 'this artist') . ' on GrinMuzik.';
                        $shareImage = $profile->getShareImageUrl();

                        $activeRouteSeo = array_merge($activeRouteSeo, [
                            'title' => $profile->stage_name ?? $profile->user?->name ?? 'Artist',
                            'description' => Str::limit(strip_tags($description), 160),
                            'canonical' => route('artist.page', $profile),
                            'type' => 'website',
                            'image' => $shareImage,
                            'image_type' => $shareImage !== '' ? 'image/jpeg' : null,
                            'image_width' => $shareImage !== '' ? 1200 : null,
                            'image_height' => $shareImage !== '' ? 1200 : null,
                            'image_alt' => $profile->avatar_alt,
                        ]);
                    }
                } elseif ($routeName === 'track.show') {
                    $track = request()->route('track');

                    if ($track instanceof Track) {
                        $track->loadMissing(['artistProfile.user', 'genre', 'album']);

                        $description = $track->description
                            ?: 'Listen to ' . $track->title . ' by ' . ($track->artistProfile?->stage_name ?? $track->artistProfile?->user?->name ?? 'Unknown artist') . ' on GrinMuzik.';

                        $activeRouteSeo = array_merge($activeRouteSeo, [
                            'title' => $track->title,
                            'description' => Str::limit(strip_tags($description), 160),
                            'canonical' => route('track.show', $track),
                            'type' => 'website',
                            'image' => $track->getShareImageUrl(),
                            'image_type' => $track->getShareImageUrl() !== '' ? 'image/jpeg' : null,
                            'image_width' => $track->getShareImageUrl() !== '' ? 1200 : null,
                            'image_height' => $track->getShareImageUrl() !== '' ? 1200 : null,
                            'image_alt' => $track->cover_alt,
                        ]);
                    }
                } elseif ($routeName === 'album.show') {
                    $album = request()->route('album');

                    if ($album instanceof Album) {
                        $album->loadMissing('artist.user', 'genre');

                        $description = $album->description
                            ?: 'Explore the ' . $album->title . ' release by ' . ($album->artist?->display_name ?? 'Unknown artist') . ' on GrinMuzik.';

                        $activeRouteSeo = array_merge($activeRouteSeo, [
                            'title' => $album->title,
                            'description' => Str::limit(strip_tags($description), 160),
                            'canonical' => route('album.show', $album),
                            'type' => 'website',
                            'image' => $album->getShareImageUrl(),
                            'image_type' => $album->getShareImageUrl() !== '' ? 'image/jpeg' : null,
                            'image_width' => $album->getShareImageUrl() !== '' ? 1200 : null,
                            'image_height' => $album->getShareImageUrl() !== '' ? 1200 : null,
                            'image_alt' => $album->cover_alt,
                            'robots' => 'index,follow',
                        ]);
                    }
                } elseif ($routeName === 'playlist.show') {
                    $playlist = request()->route('playlist');

                    if ($playlist instanceof Playlist) {
                        $playlist->loadMissing('user');

                        $description = $playlist->description
                            ?: 'A curated playlist on GrinMuzik featuring tracks selected for a shared listening mood.';

                        $activeRouteSeo = array_merge($activeRouteSeo, [
                            'title' => $playlist->name . ' Playlist',
                            'description' => Str::limit(strip_tags($description), 160),
                            'canonical' => route('playlist.show', $playlist),
                            'type' => 'website',
                            'image' => $playlist->getShareImageUrl(),
                            'image_type' => $playlist->getShareImageUrl() !== '' ? 'image/jpeg' : null,
                            'image_width' => $playlist->getShareImageUrl() !== '' ? 1200 : null,
                            'image_height' => $playlist->getShareImageUrl() !== '' ? 1200 : null,
                            'image_alt' => $playlist->cover_alt,
                            'robots' => $playlist->is_public ? 'index,follow' : 'noindex,nofollow',
                        ]);
                    }
                }

                $sharedCache[$cacheKey] = [
                    'siteSettings' => $siteSettings,
                    'siteName' => $siteName,
                    'siteTitle' => $siteTitle,
                    'themeCssVariables' => $themeCssVariables,
                    'themePrimaryColor' => $themePrimaryColor,
                    'showPublicThemeToggle' => $showPublicThemeToggle,
                    'showRegistrationRoleGuide' => $showRegistrationRoleGuide,
                    'paymentsEnabled' => $paymentsEnabled,
                    'socialLinks' => $siteSettings?->social_links ?? [],
                    'defaultSeo' => [
                        'title' => $siteTitle,
                        'description' => $defaultDescription,
                        'canonical' => request()->url(),
                        'robots' => 'index,follow',
                        'type' => 'website',
                        'image' => $siteLogo,
                        'site_name' => $siteName,
                    ],
                    'routeSeo' => $activeRouteSeo,
                    'organizationSchema' => $organizationSchema,
                    'websiteSchema' => $websiteSchema,
                ];
            }

            $view->with($sharedCache[$cacheKey]);
        });
    }

    /**
     * Make $request->ip() mean the visitor rather than a Cloudflare edge node.
     *
     * Everything that keys off the client IP depends on this — the auth
     * throttles in App\Support\AuthThrottle, the page-view analytics in
     * TrackPageView, and geolocation. Without it they all see a handful of
     * Cloudflare addresses instead of real visitors, which turns per-IP rate
     * limiting into a way for one attacker to lock out everyone.
     *
     * Trusting the CIDR list rather than '*' keeps forwarded headers ignored on
     * any request that did not actually arrive through Cloudflare.
     *
     * (Idiomatically this belongs in bootstrap/app.php's trustProxies(); it
     * lives here only because that file is not writable by the deploy user.)
     */
    protected function configureRequestTrust(): void
    {
        Request::setTrustedProxies(
            config('security.trusted_proxies', []),
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    }

    /**
     * Wire up the audit trail.
     *
     * Observers rather than controller-level calls: a track can be deleted
     * from the artist dashboard, the upload rollback, the admin API and
     * Filament, and only the model layer sees all four.
     *
     * Guarded by a Schema check so the app still boots between deploying this
     * code and running the migration — without it, every request would fail
     * on a missing table before the migration could be run.
     */
    protected function bootAuditLog(): void
    {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }
        } catch (Throwable) {
            // No database connection yet (package discovery, early console).
            return;
        }

        Track::observe(TrackObserver::class);
        ArtistProfile::observe(ArtistProfileObserver::class);
        Album::observe(AlbumObserver::class);

        // Ledger credits ride on the same model-layer principle: a purchase
        // completes from the Stripe webhook and from the Play verifier, and
        // only the observer sees both. Guarded separately because the ledger
        // table ships in a later migration than the audit log.
        if (Schema::hasTable('artist_ledger_entries')) {
            \App\Models\Donation::observe(\App\Observers\DonationObserver::class);
        }

        \Illuminate\Support\Facades\Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            // Auth::login() inside an artisan command, test or maintenance
            // script fires this event too. Recording those as sign-ins puts
            // events in the log that never happened, which is worse than a
            // gap — an audit trail is only worth anything if every row is real.
            if (app()->runningInConsole()) {
                return;
            }

            // Listener logins are high-volume and were explicitly out of
            // scope; the value here is confirming an artist or admin was
            // actually online when something changed.
            $isNoteworthy = $event->user->isArtist() || $event->user->isAdmin();

            if (! $isNoteworthy) {
                return;
            }

            app(AuditLogger::class)->record(
                'auth.login',
                $event->user,
                ['guard' => $event->guard],
                $event->user->name,
            );
        });
    }
}
