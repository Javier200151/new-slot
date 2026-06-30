<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use App\Models\Metopa;
use App\Models\Promo;
use App\Models\Status;
use App\Models\User;
use App\Policies\MetopaPolicy;
use App\Policies\PromoPolicy;
use App\Policies\StatusPolicy;
use App\Policies\UserPolicy;
use App\Policies\RolePolicy;
use App\Policies\PermissionPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Gate::policy(\App\Models\Metopa::class, \App\Policies\MetopaPolicy::class);
        Gate::policy(\App\Models\Promo::class, \App\Policies\PromoPolicy::class);
        Gate::policy(\App\Models\Status::class, \App\Policies\StatusPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\Spatie\Permission\Models\Permission::class, \App\Policies\PermissionPolicy::class);
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
