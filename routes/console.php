<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('charts:snapshot week')->weeklyOn(1, '00:10');
Schedule::command('charts:snapshot month')->monthlyOn(1, '00:20');
Schedule::command('artists:prune-dormant')->daily();
// Scheduled releases are claimed atomically, so an overlapping or duplicate
// scheduler run cannot double-publish; withoutOverlapping just avoids pile-ups.
Schedule::command('releases:publish-scheduled')->everyMinute()->withoutOverlapping();
// Spent and abandoned TV sign-in codes (see TvDeviceCode::prunable).
Schedule::command('model:prune', ['--model' => [\App\Models\TvDeviceCode::class]])->daily();
