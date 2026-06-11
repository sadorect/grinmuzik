<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TrackController;
use Illuminate\Support\Facades\Route;

// ── Read endpoints (any valid token) ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:read', 'throttle:60,1'])->group(function () {
    Route::get('/search', [SearchController::class, 'index']);

    Route::get('/tracks', [TrackController::class, 'index']);
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
});

// ── Write endpoints (token must have write ability) ────────────────────────────
Route::middleware(['auth:sanctum', 'ability:write', 'throttle:30,1'])->group(function () {
    Route::post('/tracks/{track:slug}/play', [ActionController::class, 'recordPlay']);
    Route::post('/tracks/{track:slug}/like', [ActionController::class, 'toggleLike']);

    Route::post('/playlists', [ActionController::class, 'createPlaylist']);
    Route::post('/playlists/{playlist:slug}/tracks/{track:slug}', [ActionController::class, 'addTrackToPlaylist']);
    Route::delete('/playlists/{playlist:slug}/tracks/{track:slug}', [ActionController::class, 'removeTrackFromPlaylist']);
});
