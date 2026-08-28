<?php

namespace App\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\Capabilities\CapabilityRegistry;
use App\AI\Providers\OpenAIProvider;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Contracts\ToolCallingProvider;
use App\AI\EntityResolution\EloquentEntityResolverAdapter;
use App\AI\EntityResolution\EntityResolverRegistry;
use App\Events\Prep\PrepItemAssigned;
use App\Events\Tasks\TaskAssigned;
use App\Listeners\Notifications\SendPrepItemAssignedNotification;
use App\Listeners\Notifications\SendTaskAssignedNotification;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Event;
use App\Models\Availability;
use App\Models\Menu;
use App\Models\Beo;
use App\Models\BeoVersion;
use App\Models\BeoImportBatch;
use App\Models\DocumentProcessingJob;
use App\Models\ExtractionRun;
use App\Models\Notification;
use App\Models\Recipe;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\PrepListVersion;
use App\Models\Shift;
use App\Models\Station;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Team;
use App\Models\Venue;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Policies\AvailabilityPolicy;
use App\Policies\BeoPolicy;
use App\Policies\BeoImportBatchPolicy;
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
use App\Policies\TaskPolicy;
use App\Policies\TeamPolicy;
use App\Policies\VenuePolicy;
use App\Policies\WorkspacePolicy;
use App\Observers\WorkspaceRealtimeObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // New integrations resolve the versioned CapabilityRegistry. ToolRegistry
        // stays as a container alias so legacy consumers share this same catalog.
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->alias(CapabilityRegistry::class, \App\AI\Tools\ToolRegistry::class);

        $this->app->singleton(EntityResolverRegistry::class, function (): EntityResolverRegistry {
            $simple = static fn (string $type, string $model, array $fields, array $relations = [], ?string $ability = 'view') => new EloquentEntityResolverAdapter(
                $type,
                $model,
                $fields,
                $relations,
                $ability,
                static fn ($entity): string => (string) ($entity->name ?? $entity->title ?? $entity->display_name ?? $entity->event_number ?? $entity->getKey()),
                static fn ($entity): array => array_filter([
                    'category' => $entity->category ?? null,
                    'status' => $entity->status ?? null,
                    'version' => $entity->current_version ?? null,
                ], static fn ($value): bool => $value !== null && $value !== ''),
            );

            return new EntityResolverRegistry([
                $simple('client', Client::class, ['name', 'company_name', 'email', 'phone']),
                $simple('contact', Contact::class, ['display_name', 'first_name', 'last_name', 'email', 'phone']),
                $simple('event', Event::class, ['name', 'event_number', 'service_type', 'event_type'], ['client', 'venue']),
                $simple('venue', Venue::class, ['name', 'city', 'state', 'contact_name']),
                $simple('document', Document::class, ['name', 'original_filename', 'document_type']),
                $simple('beo', Beo::class, ['name', 'reference_number', 'status']),
                $simple('menu', Menu::class, ['name', 'status'], ['currentVersionRecord']),
                $simple('recipe', Recipe::class, ['name', 'category', 'recipe_code'], ['currentVersionRecord']),
                $simple('prep_list', PrepList::class, ['name', 'status'], ['event', 'currentVersionRecord']),
                $simple('prep_item', PrepItem::class, ['title', 'status'], ['section.version.prepList']),
                $simple('task', Task::class, ['title', 'status'], ['event', 'assignments.membership.user']),
                $simple('team', Team::class, ['name', 'key', 'description'], ['leadMembership.user']),
                $simple('station', Station::class, ['name', 'key', 'description'], ['team']),
                $simple('shift', Shift::class, ['role', 'status', 'notes'], ['membership.user', 'team', 'station']),
                new EloquentEntityResolverAdapter('membership', WorkspaceMembership::class, [], ['user', 'role'], null,
                    static fn ($entity): string => (string) ($entity->user?->name ?? $entity->getKey()),
                    static fn ($entity): array => array_filter(['role' => $entity->role?->name ?? null], static fn ($value): bool => $value !== null && $value !== ''),
                ),
            ]);
        });

        $this->app->bind(AIProvider::class, function () {
            $provider = (string) config('ai.default', 'openai');

            return match ($provider) {
                'openai' => new OpenAIProvider(),
                'rule_based' => new RuleBasedAIProvider(),
                default => throw new InvalidArgumentException(
                    "Unsupported AI provider [{$provider}]."
                ),
            };
        });

        $this->app->bind(ToolCallingProvider::class, fn () => $this->app->make(OpenAIProvider::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EventFacade::listen(TaskAssigned::class, SendTaskAssignedNotification::class);
        EventFacade::listen(PrepItemAssigned::class, SendPrepItemAssignedNotification::class);

        foreach ([
            Beo::class,
            BeoVersion::class,
            Document::class,
            DocumentProcessingJob::class,
            Event::class,
            ExtractionRun::class,
            Notification::class,
            PrepItem::class,
            PrepList::class,
            PrepListVersion::class,
            Task::class,
            TaskAssignment::class,
        ] as $model) {
            $model::observe(WorkspaceRealtimeObserver::class);
        }

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Availability::class, AvailabilityPolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Beo::class, BeoPolicy::class);
        Gate::policy(BeoImportBatch::class, BeoImportBatchPolicy::class);
        Gate::policy(PrepItem::class, PrepItemPolicy::class);
        Gate::policy(PrepList::class, PrepListPolicy::class);
        Gate::policy(Recipe::class, RecipePolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Station::class, StationPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
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
