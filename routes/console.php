<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send due-date reminders for channels and todos every hour.
Schedule::command('reminders:send-due --days-ahead=0')->hourly();
