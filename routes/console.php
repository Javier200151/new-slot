<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:cleanup')
    ->dailyAt('03:30')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();

Schedule::command('activitylog:clean --force')
    ->dailyAt('03:30')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();

Schedule::command('community:birthdays')
    ->dailyAt('08:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();
