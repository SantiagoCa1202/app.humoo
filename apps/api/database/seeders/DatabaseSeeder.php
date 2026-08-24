<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspaceAccessCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            UnitSeeder::class,
        ]);

        $roles = app(WorkspaceAccessCatalog::class)
            ->ensureSystemCatalog()['roles'];

        $owner = User::query()->updateOrCreate([
            'email' => 'owner@humoo.local',
        ], [
            'name' => 'Humoo Owner',
            'password' => 'password',
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::query()->updateOrCreate([
            'slug' => 'humoo-demo-kitchen',
        ], [
            'name' => 'Humoo Demo Kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $ownerRole = $roles->get('owner');

        WorkspaceMembership::query()->updateOrCreate([
            'workspace_id' => $workspace->getKey(),
            'user_id' => $owner->getKey(),
        ], [
            'role_id' => $ownerRole->getKey(),
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach ([
            [
                'key' => 'team_members',
                'name' => 'Team members',
                'description' => 'Maximum active members allowed in the workspace.',
                'type' => 'quantity',
                'module' => 'members',
                'unit' => 'users',
                'reset_period' => 'none',
                'active' => true,
            ],
            [
                'key' => 'monthly_ai_actions',
                'name' => 'Monthly AI actions',
                'description' => 'Monthly quota for AI-assisted actions.',
                'type' => 'limit',
                'module' => 'ai',
                'unit' => 'actions',
                'reset_period' => 'monthly',
                'active' => true,
            ],
            [
                'key' => 'active_events',
                'name' => 'Active events',
                'description' => 'Concurrent active events available to the workspace.',
                'type' => 'limit',
                'module' => 'events',
                'unit' => 'events',
                'reset_period' => 'none',
                'active' => true,
            ],
            [
                'key' => 'prep_collaboration',
                'name' => 'Prep collaboration',
                'description' => 'Collaborative prep lists with assignments and audit trails.',
                'type' => 'boolean',
                'module' => 'prep',
                'unit' => null,
                'reset_period' => 'none',
                'active' => true,
            ],
        ] as $featureData) {
            Feature::query()->updateOrCreate([
                'key' => $featureData['key'],
            ], $featureData);
        }

        $plans = Plan::query()
            ->whereIn('key', ['free', 'basic', 'pro'])
            ->get()
            ->keyBy('key');

        $features = Feature::query()
            ->whereIn('key', [
                'team_members',
                'monthly_ai_actions',
                'active_events',
                'prep_collaboration',
            ])
            ->get()
            ->keyBy('key');

        $entitlements = [
            'free' => [
                'team_members' => ['enabled' => true, 'limit_value' => 1],
                'monthly_ai_actions' => ['enabled' => true, 'limit_value' => 50],
                'active_events' => ['enabled' => true, 'limit_value' => 5],
                'prep_collaboration' => ['enabled' => false, 'limit_value' => null],
            ],
            'basic' => [
                'team_members' => ['enabled' => true, 'limit_value' => 3],
                'monthly_ai_actions' => ['enabled' => true, 'limit_value' => 500],
                'active_events' => ['enabled' => true, 'limit_value' => 25],
                'prep_collaboration' => ['enabled' => true, 'limit_value' => null],
            ],
            'pro' => [
                'team_members' => ['enabled' => true, 'limit_value' => 10],
                'monthly_ai_actions' => ['enabled' => true, 'limit_value' => 2000],
                'active_events' => ['enabled' => true, 'limit_value' => 100],
                'prep_collaboration' => ['enabled' => true, 'limit_value' => null],
            ],
        ];

        foreach ($entitlements as $planKey => $featureMatrix) {
            $plan = $plans->get($planKey);

            if (!$plan) {
                continue;
            }

            foreach ($featureMatrix as $featureKey => $values) {
                $feature = $features->get($featureKey);

                if (!$feature) {
                    continue;
                }

                DB::table('plan_features')->updateOrInsert(
                    [
                        'plan_id' => $plan->getKey(),
                        'feature_id' => $feature->getKey(),
                    ],
                    [
                        'enabled' => $values['enabled'],
                        'limit_value' => $values['limit_value'],
                        'config' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $proPlan = $plans->get('pro');

        if ($proPlan) {
            Subscription::query()->updateOrCreate([
                'workspace_id' => $workspace->getKey(),
                'plan_id' => $proPlan->getKey(),
            ], [
                'provider' => 'manual',
                'provider_subscription_id' => null,
                'status' => 'active',
                'billing_interval' => 'month',
                'currency' => 'USD',
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'trial_starts_at' => null,
                'trial_ends_at' => null,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'cancel_at' => null,
                'ends_at' => null,
                'grace_ends_at' => null,
                'provider_synced_at' => now(),
                'metadata' => ['seeded' => true],
            ]);
        }
    }
}
