<?php

namespace Database\Seeders;

use App\Models\AiRequest;
use App\Models\Feature;
use App\Models\GeneratedImage;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisualStyle;
use Carbon\Carbon;
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
        $styles = [];
        foreach (['Realistic', 'Anime', 'Watercolor', 'Oil Painting', 'Digital Art'] as $i => $name) {
            $styles[] = VisualStyle::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name'        => $name,
                    'description' => "$name style preset",
                    'is_active'   => true,
                    'sort_order'  => $i,
                ]
            );
        }

        // ── Sample Users ──
        $users = collect();
        for ($i = 1; $i <= 30; $i++) {
            $user = User::firstOrCreate(
                ['email' => "user{$i}@flash.test"],
                [
                    'name'     => "User {$i}",
                    'password' => Hash::make('password'),
                    'status'   => $i <= 25 ? 'active' : 'suspended',
                    'locale'   => 'en',
                    'timezone' => 'UTC',
                ]
            );
            $user->roles()->syncWithoutDetaching([$userRole->id]);
            $users->push($user);
        }

        // ── Subscriptions ──
        $planSlugs = ['free', 'starter', 'professional', 'enterprise'];
        $users->each(function (User $user, int $idx) use ($plans, $planSlugs) {
            $planSlug = $planSlugs[$idx % count($planSlugs)];
            $plan = $plans[$planSlug];

            Subscription::firstOrCreate(
                ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active'],
                [
                    'billing_cycle'     => 'monthly',
                    'price'             => $plan->price_monthly,
                    'currency'          => 'USD',
                    'starts_at'         => Carbon::now()->subDays(rand(1, 30)),
                    'ends_at'           => Carbon::now()->addDays(rand(1, 30)),
                    'credits_remaining' => rand(0, $plan->credits_monthly),
                    'credits_total'     => $plan->credits_monthly,
                    'auto_renew'        => true,
                ]
            );
        });

        // ── AI Requests (spread over 14 days) ──
        $statuses = ['pending', 'processing', 'completed', 'completed', 'completed', 'completed', 'failed'];
        $types = ['text_to_image', 'image_to_image', 'inpainting', 'upscale'];
        $prompts = [
            'A futuristic city skyline at sunset',
            'Portrait of a cyberpunk samurai',
            'Enchanted forest with glowing mushrooms',
            'Abstract fractal art in neon colors',
            'Steampunk mechanical dragon',
            'Underwater coral reef scene',
            'Northern lights over snow mountains',
            'Vintage car in a rainy street',
        ];

        for ($i = 0; $i < 120; $i++) {
            $user = $users->random();
            $status = $statuses[array_rand($statuses)];
            $createdAt = Carbon::now()->subDays(rand(0, 13))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            $req = AiRequest::create([
                'uuid'              => Str::uuid()->toString(),
                'user_id'           => $user->id,
                'subscription_id'   => $user->subscriptions()->first()?->id,
                'visual_style_id'   => $styles[array_rand($styles)]->id,
                'type'              => $types[array_rand($types)],
                'status'            => $status,
                'user_prompt'       => $prompts[array_rand($prompts)],
                'model_used'        => 'stable-diffusion-xl',
                'engine_provider'   => 'stability',
                'width'             => 1024,
                'height'            => 1024,
                'steps'             => 30,
                'cfg_scale'         => 7.5,
                'num_images'        => 1,
                'credits_consumed'  => rand(1, 5),
                'processing_time_ms'=> $status === 'completed' ? rand(2000, 15000) : null,
                'error_message'     => $status === 'failed' ? 'Model inference timeout' : null,
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
                'started_at'        => $createdAt,
                'completed_at'      => $status === 'completed' ? $createdAt->addSeconds(rand(2, 15)) : null,
            ]);

            // Generate images for completed requests
            if ($status === 'completed') {
                GeneratedImage::create([
                    'uuid'          => Str::uuid()->toString(),
                    'user_id'       => $user->id,
                    'ai_request_id' => $req->id,
                    'file_path'     => "generated/{$req->uuid}.png",
                    'file_name'     => "{$req->uuid}.png",
                    'disk'          => 'local',
                    'mime_type'     => 'image/png',
                    'file_size'     => rand(200000, 2000000),
                    'width'         => 1024,
                    'height'        => 1024,
                    'is_public'     => false,
                    'is_favorite'   => rand(0, 1) === 1,
                    'is_nsfw'       => false,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
                ]);
            }
        }

        // ── Payments ──
        $paymentStatuses = ['completed', 'completed', 'completed', 'completed', 'pending', 'failed'];
        for ($i = 0; $i < 50; $i++) {
            $user = $users->random();
            $sub = $user->subscriptions()->first();
            $pStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $amount = [9.99, 29.99, 99.99][array_rand([9.99, 29.99, 99.99])];
            $createdAt = Carbon::now()->subDays(rand(0, 14))->subHours(rand(0, 23));

            Payment::create([
                'uuid'               => Str::uuid()->toString(),
                'user_id'            => $user->id,
                'subscription_id'    => $sub?->id,
                'payment_gateway'    => 'stripe',
                'gateway_payment_id' => 'pi_' . Str::random(24),
                'status'             => $pStatus,
                'amount'             => $amount,
                'discount_amount'    => 0,
                'tax_amount'         => round($amount * 0.1, 2),
                'net_amount'         => round($amount * 0.9, 2),
                'currency'           => 'USD',
                'payment_method'     => 'card',
                'paid_at'            => $pStatus === 'completed' ? $createdAt : null,
                'created_at'         => $createdAt,
                'updated_at'         => $createdAt,
            ]);
        }

        // ── Admin Notifications ──
        foreach ([
            ['type' => 'alert', 'title' => 'High failure rate detected', 'body' => 'AI request failure rate exceeded 10% in the last hour.', 'priority' => 'high'],
            ['type' => 'warning', 'title' => '3 pending payments', 'body' => 'There are payments awaiting processing for more than 24 hours.', 'priority' => 'normal'],
            ['type' => 'info', 'title' => 'System update available', 'body' => 'A new platform version is available. Review the changelog.', 'priority' => 'low'],
        ] as $notif) {
            Notification::firstOrCreate(
                ['title' => $notif['title'], 'user_id' => $admin->id],
                array_merge($notif, [
                    'uuid'    => Str::uuid()->toString(),
                    'user_id' => $admin->id,
                    'channel' => 'in_app',
                    'is_read' => false,
                ])
            );
        }

        $this->command->info('Admin dashboard seed data created successfully.');
    }
}
