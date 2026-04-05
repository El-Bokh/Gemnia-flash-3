<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\Subscription;
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

        // ── Admin Subscription (Enterprise plan) ──
        $enterprisePlan = $plans['enterprise'];
        Subscription::firstOrCreate(
            ['user_id' => $admin->id, 'plan_id' => $enterprisePlan->id],
            [
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'price'             => $enterprisePlan->price_monthly,
                'currency'          => $enterprisePlan->currency ?? 'USD',
                'starts_at'         => now(),
                'ends_at'           => now()->addYear(),
                'credits_remaining' => $enterprisePlan->credits_monthly,
                'credits_total'     => $enterprisePlan->credits_monthly,
                'auto_renew'        => true,
            ]
        );

        // ── Visual Styles ──
        $visualStyles = [
            [
                'name'            => 'Realistic',
                'slug'            => 'realistic',
                'description'     => 'Photo-realistic high-quality imagery',
                'prompt_prefix'   => 'Create a hyper-realistic, photographic-quality image with natural lighting, accurate shadows, and fine details.',
                'prompt_suffix'   => 'The result must look like a professional DSLR photograph with shallow depth of field and natural color grading.',
                'negative_prompt' => 'cartoon, anime, painting, sketch, low quality, blurry',
                'category'        => 'photography',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 0,
            ],
            [
                'name'            => 'Anime',
                'slug'            => 'anime',
                'description'     => 'Japanese anime and manga illustration style',
                'prompt_prefix'   => 'Create an anime-style illustration with vibrant colors, expressive eyes, clean line art, and cel-shading typical of Japanese animation.',
                'prompt_suffix'   => 'The style should resemble high-quality anime production art with vivid colors and sharp outlines.',
                'negative_prompt' => 'realistic, photograph, 3d render, blurry, low quality',
                'category'        => 'illustration',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 1,
            ],
            [
                'name'            => 'Watercolor',
                'slug'            => 'watercolor',
                'description'     => 'Soft watercolor painting aesthetic',
                'prompt_prefix'   => 'Create a beautiful watercolor painting with soft washes, transparent layers, visible brush strokes, and gentle color bleeding effects.',
                'prompt_suffix'   => 'The artwork should look like a hand-painted watercolor on textured paper with delicate gradients and organic flow.',
                'negative_prompt' => 'digital, sharp edges, photograph, 3d, neon colors',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 2,
            ],
            [
                'name'            => 'Oil Painting',
                'slug'            => 'oil-painting',
                'description'     => 'Classical oil painting masterpiece style',
                'prompt_prefix'   => 'Create a classical oil painting with rich, thick brush strokes, deep saturated colors, dramatic chiaroscuro lighting, and visible canvas texture.',
                'prompt_suffix'   => 'The painting should resemble a museum-quality masterpiece in the tradition of the Old Masters with impasto technique and warm undertones.',
                'negative_prompt' => 'digital, flat, cartoon, photograph, minimalist',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 3,
            ],
            [
                'name'            => 'Digital Art',
                'slug'            => 'digital-art',
                'description'     => 'Modern digital concept art style',
                'prompt_prefix'   => 'Create stunning digital concept art with polished rendering, dynamic composition, vibrant color palette, and epic atmosphere.',
                'prompt_suffix'   => 'The artwork should look like professional digital concept art from a major game studio or film production, with dramatic lighting and fine details.',
                'negative_prompt' => 'sketch, rough, low quality, blurry, amateur',
                'category'        => 'illustration',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 4,
            ],
            [
                'name'            => 'Cyberpunk',
                'slug'            => 'cyberpunk',
                'description'     => 'Futuristic neon-lit cyberpunk aesthetic',
                'prompt_prefix'   => 'Create a cyberpunk-themed image with neon lights, holographic displays, rain-slicked streets, futuristic technology, and a dark dystopian atmosphere.',
                'prompt_suffix'   => 'The scene should be bathed in vivid neon pink, blue, and purple lighting with high contrast, lens flares, and a gritty urban sci-fi feel.',
                'negative_prompt' => 'bright, cheerful, nature, pastoral, vintage, old-fashioned',
                'category'        => 'digital',
                'is_active'       => true,
                'is_premium'      => true,
                'sort_order'      => 5,
            ],
            [
                'name'            => 'Minimalist',
                'slug'            => 'minimalist',
                'description'     => 'Clean minimalist design with simple shapes',
                'prompt_prefix'   => 'Create a minimalist design with clean lines, simple geometric shapes, generous whitespace, and a limited color palette.',
                'prompt_suffix'   => 'The design should follow principles of simplicity and clarity, using only essential elements with a modern, elegant aesthetic.',
                'negative_prompt' => 'complex, busy, cluttered, ornate, detailed texture',
                'category'        => 'design',
                'is_active'       => true,
                'is_premium'      => false,
                'sort_order'      => 6,
            ],
            [
                'name'            => 'Pop Art',
                'slug'            => 'pop-art',
                'description'     => 'Bold pop art inspired by Warhol and Lichtenstein',
                'prompt_prefix'   => 'Create a pop art image with bold outlines, Ben-Day dots, flat vivid primary colors, and comic book aesthetics inspired by Andy Warhol and Roy Lichtenstein.',
                'prompt_suffix'   => 'The image should have the iconic pop art look with halftone patterns, speech bubbles aesthetic, and saturated contrasting colors.',
                'negative_prompt' => 'realistic, subtle, muted colors, photograph, 3d render',
                'category'        => 'art',
                'is_active'       => true,
                'is_premium'      => true,
                'sort_order'      => 7,
            ],
        ];

        foreach ($visualStyles as $styleData) {
            VisualStyle::updateOrCreate(
                ['slug' => $styleData['slug']],
                $styleData,
            );
        }

        $this->command->info('Admin dashboard structural data seeded successfully.');
    }
}
