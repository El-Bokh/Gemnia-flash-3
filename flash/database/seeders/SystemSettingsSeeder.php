<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Seed the platform settings that are still used by the admin settings area.
     */
    public function run(): void
    {
        $settings = [
            [
                'group' => 'maintenance',
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'display_name' => 'Maintenance Mode',
                'description' => 'Enable or disable maintenance mode for the platform.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 1,
            ],
            [
                'group' => 'maintenance',
                'key' => 'maintenance_message',
                'value' => 'The platform is currently under maintenance. We will be back shortly.',
                'type' => 'text',
                'display_name' => 'Maintenance Message',
                'description' => 'Message shown to users while maintenance mode is active.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 2,
            ],
            [
                'group' => 'maintenance',
                'key' => 'maintenance_allowed_ips',
                'value' => '[]',
                'type' => 'json',
                'display_name' => 'Maintenance Allowed IPs',
                'description' => 'IP addresses allowed to bypass maintenance mode.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 3,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'openai_api_key',
                'value' => Crypt::encryptString(''),
                'type' => 'string',
                'display_name' => 'OpenAI API Key',
                'description' => 'Secret API key used for OpenAI requests.',
                'is_public' => false,
                'is_encrypted' => true,
                'options' => null,
                'sort_order' => 10,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'openai_model',
                'value' => 'gpt-4.1-mini',
                'type' => 'string',
                'display_name' => 'OpenAI Model',
                'description' => 'Default model used for OpenAI text generation.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 11,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'openai_max_tokens',
                'value' => '4096',
                'type' => 'integer',
                'display_name' => 'OpenAI Max Tokens',
                'description' => 'Maximum token limit for OpenAI requests.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 12,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'stability_ai_key',
                'value' => Crypt::encryptString(''),
                'type' => 'string',
                'display_name' => 'Stability AI API Key',
                'description' => 'Secret API key used for Stability AI requests.',
                'is_public' => false,
                'is_encrypted' => true,
                'options' => null,
                'sort_order' => 20,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'stability_ai_engine',
                'value' => 'stable-diffusion-xl-1024-v1-0',
                'type' => 'string',
                'display_name' => 'Stability AI Engine',
                'description' => 'Default engine used for Stability AI image generation.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 21,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }

        Setting::clearCache();

        $this->command?->info('System settings seeded successfully.');
    }
}