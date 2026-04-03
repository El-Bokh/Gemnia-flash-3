<?php
/**
 * Verify System Settings frontend types match backend API responses.
 * Run: php verify_settings.php
 */

$base  = 'http://127.0.0.1:8099/api/admin';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$pass  = 0;
$fail  = 0;

function api(string $method, string $url, array $body = []): array {
    global $base, $token;
    $full = str_starts_with($url, '/settings/public')
        ? str_replace('/admin', '', $base) . $url
        : "{$base}{$url}";
    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true) ?? []];
}

function check(string $label, bool $condition): void {
    global $pass, $fail;
    if ($condition) { $pass++; echo "  ✅ {$label}\n"; }
    else           { $fail++; echo "  ❌ {$label}\n"; }
}

function hasKeys(array $data, array $keys): bool {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $data)) return false;
    }
    return true;
}

echo "\n═══ SYSTEM SETTINGS ═══\n";

// 1) List settings (grouped)
$r = api('GET', '/settings');
check('List settings 200', $r['code'] === 200);
$groups = $r['body']['data'] ?? [];
check('Returns array of groups', is_array($groups) && count($groups) > 0);

$settingId = null;
$boolSettingId = null;
if (!empty($groups)) {
    $g = $groups[0];
    check('Group has keys', hasKeys($g, ['group', 'count', 'settings']));
    check('Group settings is array', is_array($g['settings']));

    if (!empty($g['settings'])) {
        $s = $g['settings'][0];
        check('Setting keys', hasKeys($s, [
            'id', 'group', 'key', 'value', 'type', 'display_name',
            'description', 'is_public', 'is_encrypted', 'options',
            'sort_order', 'updated_at'
        ]));
        $settingId = $s['id'];
    }

    // Find a boolean setting for toggle test
    foreach ($groups as $grp) {
        foreach ($grp['settings'] ?? [] as $st) {
            if ($st['type'] === 'boolean') {
                $boolSettingId = $st['id'];
                break 2;
            }
        }
    }
}

// 2) Show single setting
if ($settingId) {
    $r = api('GET', "/settings/{$settingId}");
    check('Show setting 200', $r['code'] === 200);
    $d = $r['body']['data'] ?? [];
    check('Show setting keys', hasKeys($d, [
        'id', 'group', 'key', 'value', 'type', 'display_name',
        'description', 'is_public', 'is_encrypted', 'options',
        'sort_order', 'updated_at'
    ]));
}

// 3) Update setting
if ($settingId) {
    $r = api('GET', "/settings/{$settingId}");
    $currentVal = $r['body']['data']['value'] ?? '';
    $r = api('PUT', "/settings/{$settingId}", ['value' => $currentVal]);
    check('Update setting 200', $r['code'] === 200);
    check('Update returns setting', isset($r['body']['data']['id']));
}

// 4) Toggle boolean setting
if ($boolSettingId) {
    $r = api('POST', "/settings/{$boolSettingId}/toggle");
    check('Toggle setting 200', $r['code'] === 200);
    check('Toggle returns setting', isset($r['body']['data']['id']));
    // Toggle back
    api('POST', "/settings/{$boolSettingId}/toggle");
}

// 5) Bulk update
$r = api('GET', '/settings');
$firstGroup = $r['body']['data'][0] ?? null;
if ($firstGroup && !empty($firstGroup['settings'])) {
    $firstSetting = $firstGroup['settings'][0];
    $r = api('PUT', '/settings/bulk', [
        'settings' => [
            ['key' => $firstSetting['key'], 'value' => $firstSetting['value']],
        ],
    ]);
    check('Bulk update 200', $r['code'] === 200);
    $bd = $r['body']['data'] ?? [];
    check('Bulk result has updated', array_key_exists('updated', $bd));
    check('Bulk result has errors', array_key_exists('errors', $bd));
}

// 6) Reset group
if ($firstGroup) {
    $groupName = $firstGroup['group'];
    $r = api('POST', "/settings/reset/{$groupName}");
    check('Reset group 200', $r['code'] === 200);
    check('Reset returns settings array', is_array($r['body']['data'] ?? null));
}

// 7) Maintenance status
$r = api('GET', '/settings/maintenance');
check('Maintenance status 200', $r['code'] === 200);
$m = $r['body']['data'] ?? [];
check('Maintenance keys', hasKeys($m, ['is_enabled', 'message', 'allowed_ips']));

// 8) Toggle maintenance (toggle on then off)
$r = api('POST', '/settings/maintenance/toggle');
check('Toggle maintenance 200', $r['code'] === 200);
$tm = $r['body']['data'] ?? [];
check('Toggle maintenance keys', hasKeys($tm, ['is_enabled', 'message', 'allowed_ips']));
// Toggle back
api('POST', '/settings/maintenance/toggle');

// 9) Test integration
$r = api('POST', '/settings/test-integration', ['integration' => 'google_analytics']);
check('Test integration 200', $r['code'] === 200);
$ti = $r['body']['data'] ?? [];
check('Integration result keys', hasKeys($ti, ['success', 'message']));

// 10) Audit log
$r = api('GET', '/settings/audit-log?per_page=5');
check('Audit log 200', $r['code'] === 200);
check('Audit log has meta', isset($r['body']['meta']['current_page']));
check('Audit log data is array', is_array($r['body']['data'] ?? null));

if (!empty($r['body']['data'])) {
    $log = $r['body']['data'][0];
    check('Audit log entry keys', hasKeys($log, ['id', 'user_id', 'action', 'metadata', 'created_at', 'updated_at']));
}

// 11) Public settings (no auth required technically, but we send token anyway)
$r = api('GET', '/settings/public');
if ($r['code'] === 200) {
    check('Public settings 200', true);
    check('Public settings returns data', isset($r['body']['data']));
} else {
    // Route might use different base — try without /admin
    echo "  ⚠️  Public settings returned {$r['code']}, checking route\n";
    check('Public settings accessible', false);
}

// ════════════════════════════════════════════════
//  RESULTS
// ════════════════════════════════════════════════
echo "\n════════════════════════════════════\n";
echo "  ✅ Passed: {$pass}\n";
echo "  ❌ Failed: {$fail}\n";
echo "  Total:    " . ($pass + $fail) . "\n";
echo "════════════════════════════════════\n";
exit($fail > 0 ? 1 : 0);
