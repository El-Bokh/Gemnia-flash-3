<?php

namespace App\Services\Admin;

use App\Models\Setting;
use App\Models\UsageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SettingsManagementService
{
    // ──────────────────────────────────────────────
    //  LIST SETTINGS (grouped or flat)
    // ──────────────────────────────────────────────

    /**
     * List all settings, optionally filtered by group/search/type.
     * Returns settings grouped by their 'group' field.
     */
    public function list(array $filters = []): Collection
    {
        $query = Setting::query()->orderBy('group')->orderBy('sort_order');

        if (! empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', filter_var($filters['is_public'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get()->groupBy('group');
    }

    // ──────────────────────────────────────────────
    //  GET SINGLE SETTING
    // ──────────────────────────────────────────────

    public function getByKey(string $key): Setting
    {
        return Setting::where('key', $key)->firstOrFail();
    }

    public function getById(int $id): Setting
    {
        return Setting::findOrFail($id);
    }

    // ──────────────────────────────────────────────
    //  CREATE SETTING
    // ──────────────────────────────────────────────

    public function create(array $data, int $adminId): Setting
    {
        // Encrypt value if marked as encrypted
        if (! empty($data['is_encrypted']) && $data['value'] !== null) {
            $data['value'] = Crypt::encryptString((string) $data['value']);
        }

        $setting = Setting::create($data);

        Setting::clearCache();

        $this->logAction($setting, $adminId, 'setting_created', [
            'key'   => $setting->key,
            'group' => $setting->group,
        ]);

        return $setting;
    }

    // ──────────────────────────────────────────────
    //  UPDATE SINGLE SETTING
    // ──────────────────────────────────────────────

    public function update(Setting $setting, mixed $value, int $adminId): Setting
    {
        $oldValue = $setting->is_encrypted ? '***encrypted***' : $setting->value;

        // Prepare the value for storage
        $storeValue = $this->prepareValueForStorage($setting, $value);

        $setting->update(['value' => $storeValue]);

        Setting::clearCache();

        $this->logAction($setting, $adminId, 'setting_updated', [
            'key'       => $setting->key,
            'old_value' => $oldValue,
            'new_value' => $setting->is_encrypted ? '***encrypted***' : $storeValue,
        ]);

        return $setting->fresh();
    }

    // ──────────────────────────────────────────────
    //  BULK UPDATE SETTINGS
    // ──────────────────────────────────────────────

    /**
     * Update multiple settings at once.
     * Expects: [['key' => 'some_key', 'value' => 'new_value'], ...]
     */
    public function bulkUpdate(array $items, int $adminId): array
    {
        $updated = [];
        $errors  = [];

        DB::transaction(function () use ($items, $adminId, &$updated, &$errors) {
            foreach ($items as $item) {
                $setting = Setting::where('key', $item['key'])->first();

                if (! $setting) {
                    $errors[] = [
                        'key'     => $item['key'],
                        'message' => "Setting '{$item['key']}' not found.",
                    ];
                    continue;
                }

                $oldValue = $setting->is_encrypted ? '***encrypted***' : $setting->value;
                $storeValue = $this->prepareValueForStorage($setting, $item['value']);

                $setting->update(['value' => $storeValue]);

                $this->logAction($setting, $adminId, 'setting_updated', [
                    'key'       => $setting->key,
                    'old_value' => $oldValue,
                    'new_value' => $setting->is_encrypted ? '***encrypted***' : $storeValue,
                    'bulk'      => true,
                ]);

                $updated[] = $setting->fresh();
            }
        });

        Setting::clearCache();

        return [
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    // ──────────────────────────────────────────────
    //  TOGGLE FEATURE (boolean settings)
    // ──────────────────────────────────────────────

    public function toggle(Setting $setting, int $adminId): Setting
    {
        if ($setting->type !== 'boolean') {
            abort(422, "Setting '{$setting->key}' is not a boolean toggle.");
        }

        $current  = $setting->getTypedValue();
        $newValue = $current ? '0' : '1';

        $setting->update(['value' => $newValue]);

        Setting::clearCache();

        $this->logAction($setting, $adminId, 'setting_toggled', [
            'key'       => $setting->key,
            'old_value' => $current ? 'enabled' : 'disabled',
            'new_value' => $newValue === '1' ? 'enabled' : 'disabled',
        ]);

        return $setting->fresh();
    }

    // ──────────────────────────────────────────────
    //  RESET GROUP TO DEFAULTS
    // ──────────────────────────────────────────────

    public function resetGroup(string $group, int $adminId): Collection
    {
        $defaults = $this->getGroupDefaults($group);

        if (empty($defaults)) {
            abort(422, "No defaults defined for group '{$group}'.");
        }

        $settings = Setting::inGroup($group)->get();
        $resetCount = 0;

        DB::transaction(function () use ($settings, $defaults, $adminId, &$resetCount) {
            foreach ($settings as $setting) {
                if (! array_key_exists($setting->key, $defaults)) {
                    continue;
                }

                $defaultValue = $defaults[$setting->key];
                $oldValue     = $setting->is_encrypted ? '***encrypted***' : $setting->value;

                $storeValue = $setting->is_encrypted && $defaultValue !== null
                    ? Crypt::encryptString((string) $defaultValue)
                    : (string) $defaultValue;

                $setting->update(['value' => $storeValue]);

                $this->logAction($setting, $adminId, 'setting_reset', [
                    'key'       => $setting->key,
                    'old_value' => $oldValue,
                    'group'     => $setting->group,
                ]);

                $resetCount++;
            }
        });

        Setting::clearCache();

        return Setting::inGroup($group)->orderBy('sort_order')->get();
    }

    // ──────────────────────────────────────────────
    //  MAINTENANCE MODE
    // ──────────────────────────────────────────────

    public function getMaintenanceStatus(): array
    {
        return [
            'is_enabled'  => (bool) Setting::getValue('maintenance_mode', false),
            'message'     => Setting::getValue('maintenance_message', 'The platform is under maintenance. Please try again later.'),
            'allowed_ips' => Setting::getValue('maintenance_allowed_ips', []),
        ];
    }

    public function toggleMaintenance(int $adminId): array
    {
        $current = (bool) Setting::getValue('maintenance_mode', false);
        $newValue = ! $current;

        Setting::setValue('maintenance_mode', $newValue ? '1' : '0');

        $setting = Setting::where('key', 'maintenance_mode')->first();

        $this->logAction($setting, $adminId, $newValue ? 'maintenance_enabled' : 'maintenance_disabled', [
            'previous' => $current ? 'enabled' : 'disabled',
        ]);

        return $this->getMaintenanceStatus();
    }

    // ──────────────────────────────────────────────
    //  DELETE SETTING (custom/dynamic only)
    // ──────────────────────────────────────────────

    public function delete(Setting $setting, int $adminId): bool
    {
        $this->logAction($setting, $adminId, 'setting_deleted', [
            'key'   => $setting->key,
            'group' => $setting->group,
        ]);

        $result = $setting->delete();

        Setting::clearCache();

        return $result;
    }

    // ──────────────────────────────────────────────
    //  TEST INTEGRATION
    // ──────────────────────────────────────────────

    public function testIntegration(string $integration, int $adminId): array
    {
        $result = match ($integration) {
            'openai'           => $this->testOpenAI(),
            'stability_ai'    => $this->testStabilityAI(),
            'stripe'           => $this->testStripe(),
            'paypal'           => $this->testPaypal(),
            'mailgun'          => $this->testMailgun(),
            'smtp'             => $this->testSmtp(),
            'google_analytics' => $this->testGoogleAnalytics(),
            default            => ['success' => false, 'message' => "Unknown integration: {$integration}"],
        };

        $this->logAction(
            Setting::where('key', 'like', "{$integration}%")->first() ?? new Setting(['key' => $integration]),
            $adminId,
            'integration_tested',
            [
                'integration' => $integration,
                'success'     => $result['success'],
                'message'     => $result['message'],
            ]
        );

        return $result;
    }

    // ──────────────────────────────────────────────
    //  SETTINGS AUDIT LOG
    // ──────────────────────────────────────────────

    public function getAuditLog(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = UsageLog::query()
            ->where('action', 'like', 'setting%')
            ->orWhere('action', 'like', 'maintenance%')
            ->orWhere('action', 'like', 'integration%')
            ->with('user:id,name,email,avatar')
            ->orderByDesc('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        $perPage = min($filters['per_page'] ?? 20, 100);

        return $query->paginate($perPage);
    }

    // ──────────────────────────────────────────────
    //  PUBLIC SETTINGS (for frontend)
    // ──────────────────────────────────────────────

    public function getPublicSettings(): Collection
    {
        return Setting::publicOnly()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (Setting $s) {
                return [$s->key => $s->is_encrypted ? $s->getMaskedValue() : $s->getTypedValue()];
            });
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    /**
     * Prepare a value for database storage (handle type casting + encryption).
     */
    private function prepareValueForStorage(Setting $setting, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Convert to string based on type
        $stringValue = match ($setting->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json'    => is_string($value) ? $value : json_encode($value),
            default   => (string) $value,
        };

        // Encrypt if needed
        if ($setting->is_encrypted) {
            return Crypt::encryptString($stringValue);
        }

        return $stringValue;
    }

    /**
     * Get default values for a settings group.
     */
    private function getGroupDefaults(string $group): array
    {
        return match ($group) {
            'api_keys' => [
                'openai_api_key'       => '',
                'openai_model'         => 'gpt-4',
                'openai_max_tokens'    => '4096',
                'stability_ai_key'     => '',
                'stability_ai_engine'  => 'stable-diffusion-xl-1024-v1-0',
                'stripe_public_key'    => '',
                'stripe_secret_key'    => '',
                'stripe_webhook_secret'=> '',
                'paypal_client_id'     => '',
                'paypal_client_secret' => '',
                'paypal_mode'          => 'sandbox',
            ],
            'features' => [
                'ai_image_generation'   => '1',
                'ai_image_editing'      => '1',
                'ai_text_generation'    => '1',
                'user_image_upload'     => '1',
                'paid_subscriptions'    => '1',
                'free_tier_enabled'     => '1',
                'registration_enabled'  => '1',
                'social_login_enabled'  => '0',
                'api_access_enabled'    => '1',
            ],
            'general' => [
                'platform_name'       => 'Flash AI',
                'platform_logo'       => '/images/logo.png',
                'platform_favicon'    => '/images/favicon.ico',
                'theme_mode'          => 'dark',
                'default_language'    => 'en',
                'timezone'            => 'UTC',
                'date_format'         => 'Y-m-d',
                'items_per_page'      => '15',
                'seo_title'           => 'Flash AI — AI-Powered Creative Platform',
                'seo_description'     => 'Generate stunning images and content with AI.',
                'seo_keywords'        => 'AI, image generation, content creation',
                'google_analytics_id' => '',
            ],
            'notifications' => [
                'email_notifications_enabled'  => '1',
                'inapp_notifications_enabled'  => '1',
                'push_notifications_enabled'   => '0',
                'sms_notifications_enabled'    => '0',
                'notification_from_name'       => 'Flash AI',
                'notification_from_email'      => 'noreply@flash-ai.com',
                'smtp_host'                    => '',
                'smtp_port'                    => '587',
                'smtp_username'                => '',
                'smtp_password'                => '',
                'smtp_encryption'              => 'tls',
                'mailgun_domain'               => '',
                'mailgun_secret'               => '',
            ],
            'maintenance' => [
                'maintenance_mode'        => '0',
                'maintenance_message'     => 'The platform is currently under maintenance. We will be back shortly.',
                'maintenance_allowed_ips' => '[]',
            ],
            default => [],
        };
    }

    // ──────────────────────────────────────────────
    //  INTEGRATION TEST METHODS
    // ──────────────────────────────────────────────

    private function testOpenAI(): array
    {
        $apiKey = Setting::getValue('openai_api_key');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'OpenAI API key is not configured.'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                $modelCount = count($response->json('data', []));
                return ['success' => true, 'message' => "Connected successfully. {$modelCount} models available."];
            }

            return ['success' => false, 'message' => 'Authentication failed: ' . ($response->json('error.message') ?? 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testStabilityAI(): array
    {
        $apiKey = Setting::getValue('stability_ai_key');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Stability AI key is not configured.'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get('https://api.stability.ai/v1/user/account');

            if ($response->successful()) {
                $credits = $response->json('credits', 'N/A');
                return ['success' => true, 'message' => "Connected successfully. Credits remaining: {$credits}"];
            }

            return ['success' => false, 'message' => 'Authentication failed: ' . ($response->json('message') ?? 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testStripe(): array
    {
        $secretKey = Setting::getValue('stripe_secret_key');

        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Stripe secret key is not configured.'];
        }

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($secretKey, '')
                ->get('https://api.stripe.com/v1/balance');

            if ($response->successful()) {
                $balance = $response->json('available.0.amount', 0) / 100;
                $currency = strtoupper($response->json('available.0.currency', 'usd'));
                return ['success' => true, 'message' => "Connected successfully. Balance: {$balance} {$currency}"];
            }

            return ['success' => false, 'message' => 'Authentication failed: ' . ($response->json('error.message') ?? 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testPaypal(): array
    {
        $clientId     = Setting::getValue('paypal_client_id');
        $clientSecret = Setting::getValue('paypal_client_secret');
        $mode         = Setting::getValue('paypal_mode', 'sandbox');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'message' => 'PayPal client ID and/or secret not configured.'];
        }

        $baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        try {
            $response = Http::timeout(10)
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post("{$baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Connected successfully ({$mode} mode). Token obtained."];
            }

            return ['success' => false, 'message' => 'Authentication failed: ' . ($response->json('error_description') ?? 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testMailgun(): array
    {
        $domain = Setting::getValue('mailgun_domain');
        $secret = Setting::getValue('mailgun_secret');

        if (empty($domain) || empty($secret)) {
            return ['success' => false, 'message' => 'Mailgun domain and/or secret not configured.'];
        }

        try {
            $response = Http::timeout(10)
                ->withBasicAuth('api', $secret)
                ->get("https://api.mailgun.net/v3/domains/{$domain}");

            if ($response->successful()) {
                $state = $response->json('domain.state', 'unknown');
                return ['success' => true, 'message' => "Connected. Domain state: {$state}"];
            }

            return ['success' => false, 'message' => 'Authentication failed: ' . ($response->json('message') ?? 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testSmtp(): array
    {
        $host       = Setting::getValue('smtp_host');
        $port       = Setting::getValue('smtp_port', 587);
        $username   = Setting::getValue('smtp_username');
        $encryption = Setting::getValue('smtp_encryption', 'tls');

        if (empty($host)) {
            return ['success' => false, 'message' => 'SMTP host is not configured.'];
        }

        try {
            $errno  = 0;
            $errstr = '';
            $prefix = $encryption === 'ssl' ? 'ssl://' : '';
            $fp = @fsockopen($prefix . $host, (int) $port, $errno, $errstr, 10);

            if (! $fp) {
                return ['success' => false, 'message' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }

            $response = fgets($fp, 512);
            fclose($fp);

            return ['success' => true, 'message' => "SMTP server reachable at {$host}:{$port}. Response: " . trim($response)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function testGoogleAnalytics(): array
    {
        $gaId = Setting::getValue('google_analytics_id');

        if (empty($gaId)) {
            return ['success' => false, 'message' => 'Google Analytics ID is not configured.'];
        }

        // Validate format (UA-XXXXX-Y or G-XXXXXXXXXX)
        if (preg_match('/^(UA-\d{4,}-\d{1,}|G-[A-Z0-9]{10,})$/', $gaId)) {
            return ['success' => true, 'message' => "Google Analytics ID '{$gaId}' is valid format."];
        }

        return ['success' => false, 'message' => "Invalid Google Analytics ID format: '{$gaId}'"];
    }

    // ──────────────────────────────────────────────
    //  AUDIT LOGGING
    // ──────────────────────────────────────────────

    private function logAction(Setting $setting, int $adminId, string $action, array $extra = []): void
    {
        UsageLog::create([
            'user_id'  => $adminId,
            'action'   => $action,
            'metadata' => array_merge([
                'setting_id'  => $setting->id,
                'setting_key' => $setting->key,
            ], $extra),
        ]);
    }
}
