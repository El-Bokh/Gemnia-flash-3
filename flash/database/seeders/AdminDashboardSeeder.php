<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\User;
use App\Models\VisualStyle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminDashboardSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ──
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Platform administrator', 'is_default' => false]
        );

        $userRole = Role::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'description' => 'Regular user', 'is_default' => true]
        );

        // ── Admin User ──
        $admin = User::firstOrCreate(
            ['email' => 'admin@flash.test'],
            [
                'name'     => 'Flash Admin',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'locale'   => 'en',
                'timezone' => 'UTC',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // ── Plans ──
        $plans = [];
        foreach ([
            ['name' => 'Free',         'slug' => 'free',         'price_monthly' => 0,     'price_yearly' => 0,     'credits_monthly' => 10,   'is_free' => true,  'sort_order' => 1],
            ['name' => 'Starter',      'slug' => 'starter',      'price_monthly' => 9.99,  'price_yearly' => 99.99, 'credits_monthly' => 100,  'is_free' => false, 'sort_order' => 2],
            ['name' => 'Professional', 'slug' => 'professional', 'price_monthly' => 29.99, 'price_yearly' => 299.99,'credits_monthly' => 500,  'is_free' => false, 'sort_order' => 3],
            ['name' => 'Enterprise',   'slug' => 'enterprise',   'price_monthly' => 99.99, 'price_yearly' => 999.99,'credits_monthly' => 2000, 'is_free' => false, 'sort_order' => 4],
        ] as $planData) {
            $plans[$planData['slug']] = Plan::firstOrCreate(
                ['slug' => $planData['slug']],
                array_merge($planData, [
                    'currency'        => 'USD',
                    'credits_yearly'  => $planData['credits_monthly'] * 12,
                    'is_active'       => true,
                    'is_featured'     => $planData['slug'] === 'professional',
                    'trial_days'      => $planData['is_free'] ? 0 : 7,
                ])
            );
        }

        // ── Features ──
        $features = [];
        foreach ([
            ['name' => 'Text to Image',    'slug' => 'text_to_image',    'type' => 'text_to_image'],
            ['name' => 'Image to Image',   'slug' => 'image_to_image',   'type' => 'image_to_image'],
            ['name' => 'Inpainting',       'slug' => 'inpainting',       'type' => 'inpainting'],
            ['name' => 'Upscale',          'slug' => 'upscale',          'type' => 'upscale'],
        ] as $f) {
            $features[$f['slug']] = Feature::firstOrCreate(
                ['slug' => $f['slug']],
                ['name' => $f['name'], 'type' => $f['type'], 'is_active' => true, 'sort_order' => 0]
            );
        }

        // Link features to plans
        foreach ($plans as $plan) {
            foreach ($features as $feature) {
                PlanFeature::firstOrCreate(
                    ['plan_id' => $plan->id, 'feature_id' => $feature->id],
                    ['is_enabled' => true, 'usage_limit' => null, 'credits_per_use' => 1]
                );
            }
        }

        // ── Visual Styles ──
        foreach (['Realistic', 'Anime', 'Watercolor', 'Oil Painting', 'Digital Art'] as $i => $name) {
            VisualStyle::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name'        => $name,
                    'description' => "$name style preset",
                    'is_active'   => true,
                    'sort_order'  => $i,
                ]
            );
        }

        $this->command->info('Admin dashboard structural data seeded successfully.');
    }
}
