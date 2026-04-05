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
                'key' => 'gemini_api_key',
                'value' => Crypt::encryptString('AIzaSyBXriOUTqqHqTaF-l15bg08C66TlxKm_js'),
                'type' => 'string',
                'display_name' => 'Google Gemini API Key',
                'description' => 'Secret API key used for Google Gemini requests.',
                'is_public' => false,
                'is_encrypted' => true,
                'options' => null,
                'sort_order' => 10,
            ],
            [
                'group' => 'ai_integrations',
                'key' => 'gemini_model',
                'value' => 'gemini-3.1-flash-image-preview',
                'type' => 'string',
                'display_name' => 'Gemini Model',
                'description' => 'Default model used for Google Gemini text & image generation.',
                'is_public' => false,
                'is_encrypted' => false,
                'options' => null,
                'sort_order' => 11,
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