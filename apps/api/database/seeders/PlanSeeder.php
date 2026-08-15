<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'free',
                'name' => 'Free',
                'description' => 'Starter plan for solo kitchens validating the workflow.',
                'sort_order' => 1,
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'USD',
                'trial_days' => 0,
                'active' => true,
                'public' => true,
            ],
            [
                'key' => 'basic',
                'name' => 'Basic',
                'description' => 'Operational plan for small culinary teams.',
                'sort_order' => 2,
                'price_monthly' => 49,
                'price_yearly' => 490,
                'currency' => 'USD',
                'trial_days' => 14,
                'active' => true,
                'public' => true,
            ],
            [
                'key' => 'pro',
                'name' => 'Pro',
                'description' => 'Advanced plan for collaborative production and AI-heavy operations.',
                'sort_order' => 3,
                'price_monthly' => 149,
                'price_yearly' => 1490,
                'currency' => 'USD',
                'trial_days' => 14,
                'active' => true,
                'public' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                [
                    'key' => $plan['key'],
                ],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'sort_order' => $plan['sort_order'],
                    'price_monthly' => $plan['price_monthly'],
                    'price_yearly' => $plan['price_yearly'],
                    'currency' => $plan['currency'],
                    'trial_days' => $plan['trial_days'],
                    'active' => $plan['active'],
                    'public' => $plan['public'],
                ]
            );
        }
    }
}
