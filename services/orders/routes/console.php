<?php

use Illuminate\Support\Facades\Schedule;

// Run by the orders-scheduler container (php artisan schedule:work).
Schedule::command('orders:expire-pending')->everyMinute();
