<?php

namespace App\Services\Admin;

use App\Models\Setting;
use App\Models\UsageLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SettingsManagementService
{
    // ──────────────────────────────────────────────
    //  SETTINGS AUDIT LOG
    // ──────────────────────────────────────────────

    public function getAuditLog(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = UsageLog::query()
            ->where(function ($q) {
                $q->where('action', 'like', 'setting%')
                  ->orWhere('action', 'like', 'maintenance%')
                  ->orWhere('action', 'like', 'integration%');
            })
            ->with('user:id,name,email,avatar')
            ->orderByDesc('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        $perPage = min($filters['per_page'] ?? 20, 100);

        return $query->paginate($perPage);
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
        $current  = (bool) Setting::getValue('maintenance_mode', false);
        $newValue = ! $current;

        Setting::setValue('maintenance_mode', $newValue ? '1' : '0');

        $setting = Setting::where('key', 'maintenance_mode')->first();

        if ($setting) {
            $this->logAction($setting, $adminId, $newValue ? 'maintenance_enabled' : 'maintenance_disabled', [
                'previous' => $current ? 'enabled' : 'disabled',
            ]);
        }

        return $this->getMaintenanceStatus();
    }

    public function updateMaintenanceSettings(int $adminId, array $data): array
    {
        if (array_key_exists('message', $data)) {
            Setting::setValue('maintenance_message', $data['message'] ?? '');
        }

        if (array_key_exists('allowed_ips', $data)) {
            Setting::setValue('maintenance_allowed_ips', json_encode($data['allowed_ips'] ?? []));
        }

        $setting = Setting::where('key', 'maintenance_mode')->first();
        if ($setting) {
            $this->logAction($setting, $adminId, 'maintenance_settings_updated', $data);
        }

        return $this->getMaintenanceStatus();
    }

    // ──────────────────────────────────────────────
    //  AI INTEGRATIONS
    // ──────────────────────────────────────────────

    public function getAiIntegrations(): array
    {
        $keys = [
            'gemini_auth_method',
            'gemini_api_key',
            'gemini_text_model',
            'gemini_image_model',
            'gemini_video_model',
        ];

        $settings = Setting::whereIn('key', $keys)->orderBy('sort_order')->get();

        return $settings->map(function (Setting $s) {
            return [
                'id'           => $s->id,
                'key'          => $s->key,
                'value'        => $s->is_encrypted ? $s->getMaskedValue() : $s->getTypedValue(),
                'type'         => $s->type,
                'display_name' => $s->display_name,
                'description'  => $s->description,
                'is_encrypted' => $s->is_encrypted,
                'group'        => $s->group,
            ];
        })->values()->toArray();
    }

    public function updateAiIntegrations(array $items, int $adminId): array
    {
        $updated = [];
        $errors  = [];

        $allowedKeys = [
            'gemini_auth_method',
            'gemini_api_key',
            'gemini_text_model',
            'gemini_image_model',
            'gemini_video_model',
        ];

        DB::transaction(function () use ($items, $adminId, $allowedKeys, &$updated, &$errors) {
            foreach ($items as $item) {
                if (! in_array($item['key'], $allowedKeys, true)) {
                    $errors[] = ['key' => $item['key'], 'message' => 'Not an AI integration setting.'];
                    continue;
                }

                $setting = Setting::where('key', $item['key'])->first();
                if (! $setting) {
                    $errors[] = ['key' => $item['key'], 'message' => "Setting '{$item['key']}' not found."];
                    continue;
                }

                $oldValue    = $setting->is_encrypted ? '***encrypted***' : $setting->value;
                $storeValue  = $this->prepareValueForStorage($setting, $item['value']);

                $setting->update(['value' => $storeValue]);

                $this->logAction($setting, $adminId, 'setting_updated', [
                    'key'       => $setting->key,
                    'old_value' => $oldValue,
                    'new_value' => $setting->is_encrypted ? '***encrypted***' : $storeValue,
                ]);

                $updated[] = [
                    'key'   => $setting->key,
                    'value' => $setting->is_encrypted ? $setting->fresh()->getMaskedValue() : $setting->fresh()->getTypedValue(),
                ];
            }
        });

        Setting::clearCache();

        return ['updated' => $updated, 'errors' => $errors];
    }

    // ──────────────────────────────────────────────
    //  TEST AI INTEGRATION
    // ──────────────────────────────────────────────

    public function testIntegration(string $integration, int $adminId): array
    {
        $result = match ($integration) {
            'gemini'        => $this->testGemini(),
            default         => ['success' => false, 'message' => "Unknown AI integration: {$integration}"],
        };

        $setting = Setting::where('key', 'like', "{$integration}%")->first();
        $this->logAction(
            $setting ?? new Setting(['key' => $integration]),
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
    //  PRIVATE: AI TEST METHODS
    // ──────────────────────────────────────────────

    private function testGemini(): array
    {
        $geminiService = new \App\Services\GeminiService();
        return $geminiService->testConnection();
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function prepareValueForStorage(Setting $setting, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = match ($setting->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json'    => is_string($value) ? $value : json_encode($value),
            default   => (string) $value,
        };

        if ($setting->is_encrypted) {
            return Crypt::encryptString($stringValue);
        }

        return $stringValue;
    }

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
