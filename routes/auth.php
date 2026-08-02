<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
 * These are Volt components, so a `throttle` middleware here would only limit
 * how often the PAGE may be fetched — the submissions themselves go to
 * /livewire/update and never pass through this route's middleware. The limits
 * that matter run inside each component via App\Support\AuthThrottle.
 *
 * A modest page-fetch throttle is kept anyway: it costs nothing and blunts the
 * crudest scraping, but it is not the brute-force defence.
 */
Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->name('register')
        ->middleware('throttle:30,1');

    Volt::route('login', 'pages.auth.login')
        ->name('login')
        ->middleware('throttle:30,1');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request')
        ->middleware('throttle:30,1');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset')
        ->middleware('throttle:30,1');
});

Route::middleware('auth')->group(function () {
    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');

    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
        $request->fulfill();
        $request->user()?->refresh()->syncArtistVerificationState();

        return redirect()
            ->route('dashboard')
            ->with('status', 'email-verified');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('email/verification-notification', function (\Illuminate\Http\Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
});
