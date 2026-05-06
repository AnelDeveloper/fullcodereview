<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Check and update goal logs every 15 minutes
Schedule::command('goals:check-and-update-logs')->everyFifteenMinutes();

// Monitor failed jobs and send email alerts every 15 minutes
Schedule::command('jobs:monitor-failed')->everyFifteenMinutes();
