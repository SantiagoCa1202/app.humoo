<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\Workspace;
use App\Policies\EventPolicy;
use App\Policies\PrepItemPolicy;
use App\Policies\PrepListPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

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
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(PrepItem::class, PrepItemPolicy::class);
        Gate::policy(PrepList::class, PrepListPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                sprintf(
                    'auth-login:%s|%s',
                    (string) $request->ip(),
                    strtolower((string) $request->input('email', '')),
                )
            );
        });

        RateLimiter::for('auth-password-reset', function (Request $request) {
            return Limit::perMinute(5)->by(
                sprintf(
                    'auth-password-reset:%s|%s',
                    (string) $request->ip(),
                    strtolower((string) $request->input('email', '')),
                )
            );
        });
    }
}
