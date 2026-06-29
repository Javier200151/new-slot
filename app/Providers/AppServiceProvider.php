<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */


    public function boot(): void
    {

        Activity::saving(function (Activity $activity) {
            if (app()->runningInConsole()) {
                return;
            }

            $activity->ip_address = request()->ip();
            $activity->user_agent = request()->userAgent();
            $activity->url = request()->fullUrl();
        });
    }
}
