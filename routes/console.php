<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('certificates:alert')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('tech-defects:overdue-alerts')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('shipping-calendar:reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
