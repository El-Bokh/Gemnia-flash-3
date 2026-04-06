<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription lifecycle commands
Schedule::command('subscriptions:renew')->dailyAt('00:30');
Schedule::command('subscriptions:expire')->dailyAt('01:00');
