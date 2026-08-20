<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Event;
use App\Models\Availability;
use App\Models\Menu;
use App\Models\Beo;
use App\Models\Recipe;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\Shift;
use App\Models\Station;
use App\Models\Team;
use App\Models\Venue;
use App\Models\Workspace;
use App\Policies\AvailabilityPolicy;
use App\Policies\BeoPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ContactPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EventPolicy;
use App\Policies\MenuPolicy;
use App\Policies\PrepItemPolicy;
use App\Policies\PrepListPolicy;
use App\Policies\RecipePolicy;
use App\Policies\ShiftPolicy;
use App\Policies\StationPolicy;
use App\Policies\TeamPolicy;
use App\Policies\VenuePolicy;
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
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Availability::class, AvailabilityPolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Beo::class, BeoPolicy::class);
        Gate::policy(PrepItem::class, PrepItemPolicy::class);
        Gate::policy(PrepList::class, PrepListPolicy::class);
        Gate::policy(Recipe::class, RecipePolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Station::class, StationPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
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
