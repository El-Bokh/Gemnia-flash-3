<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'display_name',
        'description',
        'is_public',
        'is_encrypted',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    //  CACHE KEY
    // ──────────────────────────────────────────────

    public const CACHE_KEY = 'system_settings';
    public const CACHE_TTL = 86400; // 24 hours

    // ──────────────────────────────────────────────
    //  STATIC HELPERS
    // ──────────────────────────────────────────────

    /**
     * Get a setting value by key (from cache first).
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = static::getAllCached();

        if (! isset($settings[$key])) {
            return $default;
        }

        return $settings[$key]['typed_value'] ?? $default;
    }

    /**
     * Set a setting value by key and refresh cache.
     */
    public static function setValue(string $key, mixed $value): bool
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return false;
        }

        $storeValue = $setting->is_encrypted
            ? Crypt::encryptString((string) $value)
            : (string) $value;

        $setting->update(['value' => $storeValue]);

        static::clearCache();

        return true;
    }

    /**
     * Get all settings as a keyed array (cached).
     */
    public static function getAllCached(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->keyBy('key')
                ->map(fn (self $s) => [
                    'id'           => $s->id,
                    'group'        => $s->group,
                    'key'          => $s->key,
                    'raw_value'    => $s->value,
                    'typed_value'  => $s->getTypedValue(),
                    'type'         => $s->type,
                    'display_name' => $s->display_name,
                    'description'  => $s->description,
                    'is_public'    => $s->is_public,
                    'is_encrypted' => $s->is_encrypted,
                    'options'      => $s->options,
                    'sort_order'   => $s->sort_order,
                ])
                ->toArray();
        });
    }

    /**
     * Clear the settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    // ──────────────────────────────────────────────
    //  VALUE CASTING / DECRYPTION
    // ──────────────────────────────────────────────

    /**
     * Get the decrypted + type-cast value.
     */
    public function getTypedValue(): mixed
    {
        $raw = $this->value;

        if ($raw === null || $raw === '') {
            return $this->type === 'boolean' ? false : null;
        }

        // Decrypt if needed
        if ($this->is_encrypted) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Exception) {
                return null;
            }
        }

        return match ($this->type) {
            'boolean'  => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer'  => (int) $raw,
            'float'    => (float) $raw,
            'json'     => json_decode($raw, true),
            default    => $raw, // string, text, etc.
        };
    }

    /**
     * Return a masked version of the value (for encrypted/sensitive fields).
     */
    public function getMaskedValue(): ?string
    {
        if (! $this->is_encrypted || $this->value === null || $this->value === '') {
            return $this->value;
        }

        try {
            $decrypted = Crypt::decryptString($this->value);

            if (strlen($decrypted) <= 8) {
                return str_repeat('•', strlen($decrypted));
            }

            // Show last 4 chars only
            return str_repeat('•', strlen($decrypted) - 4) . substr($decrypted, -4);
        } catch (\Exception) {
            return '••••••••';
        }
    }

    // ──────────────────────────────────────────────
    //  SCOPES
    // ──────────────────────────────────────────────

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopePublicOnly($query)
    {
        return $query->where('is_public', true);
    }
}
