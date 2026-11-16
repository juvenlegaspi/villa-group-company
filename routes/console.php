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

Schedule::command('haruhay:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('haruhay:send-billing-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('haruhay:send-collection-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('haruhay:due-reminders')->dailyAt('09:00')->timezone('Asia/Manila')->withoutOverlapping();
Schedule::command('haruhay:send-tracking-codes')->everyMinute()->withoutOverlapping();
