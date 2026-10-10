<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reconciliation:run --layer=wallet')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('reconciliation:run --layer=provider')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('reconciliation:run --layer=settlement')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('reconciliation:alert')->dailyAt('07:00')->withoutOverlapping();
