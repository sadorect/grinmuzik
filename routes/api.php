<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\Admin\ArtistAdminController;
use App\Http\Controllers\Api\Admin\HealthController;
use App\Http\Controllers\Api\Admin\PlaySpikesController;
use App\Http\Controllers\Api\Admin\QueueController;
use App\Http\Controllers\Api\Admin\RevenueController;
use App\Http\Controllers\Api\Admin\TrackAdminController;
use App\Http\Controllers\Api\Admin\UnderperformingTracksController;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\TrendingController;
use Illuminate\Support\Facades\Route;

// ── Read endpoints (any valid token) ──────────────────────────────────────────
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/search', [SearchController::class, 'index']);

    Route::get('/tracks', [TrackController::class, 'index']);
    Route::get('/tracks/trending', [TrendingController::class, 'index']);
    Route::get('/tracks/{track:slug}', [TrackController::class, 'show']);

    Route::get('/artists', [ArtistController::class, 'index']);
    Route::get('/artists/{artist:slug}', [ArtistController::class, 'show']);

    Route::get('/albums', [AlbumController::class, 'index']);
    Route::get('/albums/{album:slug}', [AlbumController::class, 'show']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::get('/playlists/{playlist:slug}', [PlaylistController::class, 'show']);

    Route::get('/genres', [GenreController::class, 'index']);
    Route::get('/charts', [ChartController::class, 'index']);
    Route::get('/stats', [StatsController::class, 'index']);

    // Phase 3 – MCP Resources
    Route::get('/resources/guidelines', [ResourceController::class, 'guidelines']);
    Route::get('/resources/genres',     [ResourceController::class, 'genres']);
    Route::get('/resources/contract',   [ResourceController::class, 'contract']);
});

// ── Write endpoints (token must have write ability) ────────────────────────────
Route::middleware(['auth:sanctum', 'ability:write', 'throttle:30,1'])->group(function () {
    Route::post('/tracks/{track:slug}/play', [ActionController::class, 'recordPlay']);
    Route::post('/tracks/{track:slug}/like', [ActionController::class, 'toggleLike']);

    Route::post('/playlists', [ActionController::class, 'createPlaylist']);
    Route::post('/playlists/{playlist:slug}/tracks/{track:slug}', [ActionController::class, 'addTrackToPlaylist']);
    Route::delete('/playlists/{playlist:slug}/tracks/{track:slug}', [ActionController::class, 'removeTrackFromPlaylist']);
});

// ── Admin endpoints (auth + write ability) ────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:write', 'throttle:30,1'])->prefix('admin')->group(function () {
    // Phase 1 – Track admin
    Route::patch('/tracks/{track:slug}/feature',   [TrackAdminController::class, 'feature']);
    Route::patch('/tracks/{track:slug}',           [TrackAdminController::class, 'update']);
    Route::patch('/tracks/{track:slug}/publish',   [TrackAdminController::class, 'publish']);
    Route::patch('/tracks/{track:slug}/unpublish', [TrackAdminController::class, 'unpublish']);

    // Phase 1 – Artist admin
    Route::delete('/artists/{artist:slug}',        [ArtistAdminController::class, 'destroy']);
    Route::patch('/artists/{artist:slug}/verify',  [ArtistAdminController::class, 'verify']);
    Route::post('/artists/{artist:slug}/notify',   [ArtistAdminController::class, 'notify']);

    // Phase 2 – Intelligence
    Route::get('/health',                    [HealthController::class, 'index']);
    Route::get('/tracks/underperforming',    [UnderperformingTracksController::class, 'index']);
    Route::get('/tracks/spikes',             [PlaySpikesController::class, 'index']);
    Route::get('/queue/status',              [QueueController::class, 'status']);
    Route::get('/revenue',                   [RevenueController::class, 'index']);
});
