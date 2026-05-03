<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanFeatureLimitsSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────
        //  Ensure additional features exist
        // ──────────────────────────────────────────────

        $additionalFeatures = [
            [
                'name'        => 'Background Removal',
                'slug'        => 'background_removal',
                'type'        => 'other',
                'description' => 'Remove backgrounds from images automatically',
                'is_active'   => true,
                'sort_order'  => 5,
            ],
            [
                'name'        => 'Batch Processing',
                'slug'        => 'batch_processing',
                'type'        => 'other',
                'description' => 'Process multiple images in a single batch',
                'is_active'   => true,
                'sort_order'  => 6,
            ],
            [
                'name'        => 'HD Resolution',
                'slug'        => 'hd_resolution',
                'type'        => 'other',
                'description' => 'Generate images in HD (1024x1024 and above)',
                'is_active'   => true,
                'sort_order'  => 7,
            ],
            [
                'name'        => 'Custom Styles',
                'slug'        => 'custom_styles',
                'type'        => 'other',
                'description' => 'Use custom visual styles for image generation',
                'is_active'   => true,
                'sort_order'  => 8,
            ],
            [
                'name'        => 'API Access',
                'slug'        => 'api_access',
                'type'        => 'other',
                'description' => 'Programmatic API access for generation',
                'is_active'   => true,
                'sort_order'  => 9,
            ],
            [
                'name'        => 'Priority Queue',
                'slug'        => 'priority_queue',
                'type'        => 'other',
                'description' => 'Skip queue for faster processing',
                'is_active'   => true,
                'sort_order'  => 10,
            ],
            [
                'name'        => 'Video Generation',
                'slug'        => 'video_generation',
                'type'        => 'other',
                'description' => 'Generate short videos with Vertex AI Veo',
                'is_active'   => true,
                'sort_order'  => 11,
            ],
        ];

        foreach ($additionalFeatures as $fData) {
            Feature::firstOrCreate(
                ['slug' => $fData['slug']],
                $fData
            );
        }

        // Also update description/sort_order on existing core features
        $coreUpdates = [
            'text_to_image'  => ['description' => 'Generate images from text prompts',         'sort_order' => 1],
            'image_to_image' => ['description' => 'Transform an existing image with prompts',  'sort_order' => 2],
            'inpainting'     => ['description' => 'Edit specific parts of an image',           'sort_order' => 3],
            'upscale'        => ['description' => 'Upscale images to higher resolution',       'sort_order' => 4],
        ];

        foreach ($coreUpdates as $slug => $data) {
            Feature::where('slug', $slug)->update($data);
        }

        // ──────────────────────────────────────────────
        //  Define per-plan feature limits
        // ──────────────────────────────────────────────

        $allFeatures = Feature::pluck('id', 'slug');
        $allPlans    = Plan::pluck('id', 'slug');

        //  Format: feature_slug => [plan_slug => [is_enabled, usage_limit, limit_period, credits_per_use, constraints]]
        $limitsConfig = [
            'text_to_image' => [
                'free'         => [true,  10,    'day',      1,  ['max_resolution' => '512x512']],
                'starter'      => [true,  50,    'day',      1,  ['max_resolution' => '768x768']],
                'professional' => [true,  200,   'day',      1,  ['max_resolution' => '1024x1024']],
                'enterprise'   => [true,  null,  'day',      1,  ['max_resolution' => '2048x2048']],
            ],
            'image_to_image' => [
                'free'         => [true,  5,     'day',      2,  ['max_resolution' => '512x512']],
                'starter'      => [true,  25,    'day',      1,  ['max_resolution' => '768x768']],
                'professional' => [true,  100,   'day',      1,  ['max_resolution' => '1024x1024']],
                'enterprise'   => [true,  null,  'day',      1,  ['max_resolution' => '2048x2048']],
            ],
            'inpainting' => [
                'free'         => [false, 0,     'day',      3,  null],
                'starter'      => [true,  10,    'day',      2,  ['max_resolution' => '768x768']],
                'professional' => [true,  50,    'day',      1,  ['max_resolution' => '1024x1024']],
                'enterprise'   => [true,  null,  'day',      1,  ['max_resolution' => '2048x2048']],
            ],
            'upscale' => [
                'free'         => [false, 0,     'day',      2,  null],
                'starter'      => [true,  5,     'day',      2,  ['max_factor' => '2x']],
                'professional' => [true,  30,    'day',      1,  ['max_factor' => '4x']],
                'enterprise'   => [true,  null,  'day',      1,  ['max_factor' => '8x']],
            ],
            'background_removal' => [
                'free'         => [false, 0,     'day',      2,  null],
                'starter'      => [true,  10,    'day',      1,  null],
                'professional' => [true,  50,    'day',      1,  null],
                'enterprise'   => [true,  null,  'day',      1,  null],
            ],
            'batch_processing' => [
                'free'         => [false, 0,     'day',      5,  null],
                'starter'      => [false, 0,     'day',      3,  null],
                'professional' => [true,  10,    'day',      1,  ['max_batch_size' => 5]],
                'enterprise'   => [true,  null,  'day',      1,  ['max_batch_size' => 50]],
            ],
            'hd_resolution' => [
                'free'         => [false, 0,     'month',    0,  null],
                'starter'      => [true,  20,    'month',    0,  null],
                'professional' => [true,  null,  'month',    0,  null],
                'enterprise'   => [true,  null,  'month',    0,  null],
            ],
            'custom_styles' => [
                'free'         => [false, 0,     'month',    0,  null],
                'starter'      => [false, 0,     'month',    0,  null],
                'professional' => [true,  5,     'month',    0,  ['max_custom_styles' => 5]],
                'enterprise'   => [true,  null,  'month',    0,  ['max_custom_styles' => 999]],
            ],
            'api_access' => [
                'free'         => [false, 0,     'month',    0,  null],
                'starter'      => [false, 0,     'month',    0,  null],
                'professional' => [true,  1000,  'month',    1,  ['rate_limit' => '60/min']],
                'enterprise'   => [true,  null,  'month',    1,  ['rate_limit' => '300/min']],
            ],
            'priority_queue' => [
                'free'         => [false, 0,     'month',    0,  null],
                'starter'      => [false, 0,     'month',    0,  null],
                'professional' => [false, 0,     'month',    0,  null],
                'enterprise'   => [true,  null,  'month',    0,  null],
            ],
            'video_generation' => [
                'free'         => [false, 0,     'day',      10, null],
                'starter'      => [true,  5,     'day',      10, ['max_duration_seconds' => 4, 'max_resolution' => '720p']],
                'professional' => [true,  20,    'day',      10, ['max_duration_seconds' => 8, 'max_resolution' => '1080p']],
                'enterprise'   => [true,  null,  'day',      10, ['max_duration_seconds' => 8, 'max_resolution' => '1080p']],
            ],
        ];

        foreach ($limitsConfig as $featureSlug => $planLimits) {
            $featureId = $allFeatures[$featureSlug] ?? null;
            if (! $featureId) {
                continue;
            }

            foreach ($planLimits as $planSlug => [$isEnabled, $usageLimit, $limitPeriod, $creditsPerUse, $constraints]) {
                $planId = $allPlans[$planSlug] ?? null;
                if (! $planId) {
                    continue;
                }

                PlanFeature::updateOrCreate(
                    ['plan_id' => $planId, 'feature_id' => $featureId],
                    [
                        'is_enabled'      => $isEnabled,
                        'usage_limit'     => $usageLimit,
                        'limit_period'    => $limitPeriod,
                        'credits_per_use' => $creditsPerUse,
                        'constraints'     => $constraints ?: null,
                    ]
                );
            }
        }
    }
}
