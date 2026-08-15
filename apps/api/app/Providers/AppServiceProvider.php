<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Policies\EventPolicy;
use App\Policies\PrepItemPolicy;
use App\Policies\PrepListPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
