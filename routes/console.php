<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lead module: morning push reminder for follow-ups due today / overdue (needs cron: schedule:run)
Schedule::command('lead:send-followup-reminders')->dailyAt('09:00')->timezone('Asia/Kolkata');
