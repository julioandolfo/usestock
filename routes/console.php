<?php

use App\Jobs\CleanExpiredDownloadsJob;
use App\Jobs\SyncProvidersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncProvidersJob)->hourly()->onOneServer()->withoutOverlapping();
Schedule::job(new CleanExpiredDownloadsJob)->dailyAt('03:00')->onOneServer()->withoutOverlapping();
