<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $extendedTypes = [
        'text_to_image',
        'image_to_image',
        'inpainting',
        'upscale',
        'chat',
        'styled_chat',
        'multimodal',
        'video_generation',
        'text_to_video',
        'image_to_video',
        'other',
    ];

    private array $originalTypes = [
        'text_to_image',
        'image_to_image',
        'inpainting',
        'upscale',
        'other',
    ];

    public function up(): void
    {
        $this->alterFeatureTypeEnum($this->extendedTypes);

        $now = now();
        $featureId = DB::table('features')->where('slug', 'video_generation')->value('id');

        if ($featureId) {
            DB::table('features')
                ->where('id', $featureId)
                ->update([
                    'name' => 'Video Generation',
                    'type' => 'video_generation',
                    'description' => 'Generate short AI videos from prompts and image references.',
                    'sort_order' => 11,
                    'updated_at' => $now,
                ]);
        } else {
            $featureId = DB::table('features')->insertGetId([
                'name' => 'Video Generation',
                'slug' => 'video_generation',
                'type' => 'video_generation',
                'description' => 'Generate short AI videos from prompts and image references.',
                'is_active' => true,
                'sort_order' => 11,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('plans')
            ->select(['id', 'is_free'])
            ->orderBy('id')
            ->chunkById(100, function ($plans) use ($featureId, $now) {
                foreach ($plans as $plan) {
                    $existing = DB::table('plan_features')
                        ->where('plan_id', $plan->id)
                        ->where('feature_id', $featureId)
                        ->first();

                    if ($existing) {
                        continue;
                    }

                    DB::table('plan_features')->insert([
                        'plan_id' => $plan->id,
                        'feature_id' => $featureId,
                        'is_enabled' => ! (bool) $plan->is_free,
                        'usage_limit' => (bool) $plan->is_free ? 0 : null,
                        'limit_period' => (bool) $plan->is_free ? 'day' : 'lifetime',
                        'credits_per_use' => 10,
                        'constraints' => (bool) $plan->is_free ? null : json_encode([
                            'max_duration_seconds' => 8,
                            'max_resolution' => '1080p',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('features')
            ->whereNotIn('type', $this->originalTypes)
            ->update(['type' => 'other']);

        $this->alterFeatureTypeEnum($this->originalTypes);
    }

    private function alterFeatureTypeEnum(array $types): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $enum = collect($types)
            ->map(fn (string $type) => "'".str_replace("'", "''", $type)."'")
            ->implode(',');

        DB::statement("ALTER TABLE features MODIFY type ENUM({$enum}) NOT NULL DEFAULT 'text_to_image'");
    }
};