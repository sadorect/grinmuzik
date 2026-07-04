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
