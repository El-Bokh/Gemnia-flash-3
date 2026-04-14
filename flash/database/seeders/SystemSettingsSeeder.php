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
                'key' => 'gemini_auth_method',
                'value' => 'service_account',
                'type' => 'string',
                'display_name' => 'Gemini Auth Method',
                'description' => 'Authentication method: "api_key" for simple API key, "service_account" for Google Cloud Service Account (Vertex AI).',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => '["api_key","service_account"]',
                'sort_order' => 9,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'gemini_api_key',
                'value' => Crypt::encryptString('AIzaSyBXriOUTqqHqTaF-l15bg08C66TlxKm_js'),
                'type' => 'string',
                'display_name' => 'Google Gemini API Key',
                'description' => 'Secret API key used for Google Gemini requests (only used when auth method is api_key).',
                'is_public' => false,
                'is_encrypted' => true,
                'options' => null,
                'sort_order' => 10,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'gemini_text_model',
                'value' => 'gemini-2.5-flash',
                'type' => 'string',
                'display_name' => 'Gemini Text Model',
                'description' => 'Model used for text/language chat (no image generation).',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 11,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'gemini_image_model',
                'value' => 'gemini-3.1-flash-image-preview',
                'type' => 'string',
                'display_name' => 'Image Generation Model',
                'description' => 'Model used for image generation (Gemini 3.1 Flash Image Preview / Nano Banana 2).',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 12,
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