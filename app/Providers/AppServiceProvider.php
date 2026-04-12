<?php

namespace App\Providers;

use App\Events\LeaveRequestCreated;
use App\Events\LeaveRequestReviewed;
use App\Listeners\LogLeaveAuditTrail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(LeaveRequestCreated::class, LogLeaveAuditTrail::class);
        Event::listen(LeaveRequestReviewed::class, LogLeaveAuditTrail::class);
    }
}
