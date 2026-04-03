<?php
/**
 * System Settings API — Comprehensive Integration Test
 * Run: php test_settings.php
 */

$base    = 'http://127.0.0.1:8099/api';
$token   = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$headers = [
    "Authorization: Bearer {$token}",
    'Accept: application/json',
    'Content-Type: application/json',
];

$pass = 0;
$fail = 0;

function req(string $method, string $url, ?array $body = null): array
{
    global $base, $headers;
    $ch = curl_init("{$base}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => json_decode($resp, true) ?? [], 'raw' => $resp];
}

function test(string $label, bool $pass_condition, $detail = null): void
{
    global $pass, $fail;
    if ($pass_condition) {
        echo "  ✅ PASS — {$label}\n";
        $pass++;
    } else {
        echo "  ❌ FAIL — {$label}\n";
        if ($detail) echo "     Detail: " . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "\n";
        $fail++;
    }
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 1) LIST ALL SETTINGS (grouped)\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings');
test('Status 200', $r['code'] === 200);
test('Has data array', isset($r['body']['data']));
if (isset($r['body']['data'])) {
    $groups = array_column($r['body']['data'], 'group');
    test('Contains api_keys group', in_array('api_keys', $groups));
    test('Contains features group', in_array('features', $groups));
    test('Contains general group', in_array('general', $groups));
    test('Contains notifications group', in_array('notifications', $groups));
    test('Contains maintenance group', in_array('maintenance', $groups));
    echo "  Groups found: " . implode(', ', $groups) . "\n";
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 2) LIST SETTINGS — FILTER BY GROUP (features)\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings?group=features');
test('Status 200', $r['code'] === 200);
if (isset($r['body']['data'])) {
    $allFeatures = true;
    foreach ($r['body']['data'] as $grp) {
        if ($grp['group'] !== 'features') $allFeatures = false;
    }
    test('Only features group returned', $allFeatures);
    // Count settings within the features group
    $featureCount = 0;
    foreach ($r['body']['data'] as $grp) {
        if (isset($grp['settings'])) $featureCount = count($grp['settings']);
    }
    test("Features group has 9 settings", $featureCount === 9, "Got: {$featureCount}");
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 3) LIST SETTINGS — SEARCH\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings?search=openai');
test('Status 200', $r['code'] === 200);
if (isset($r['body']['data'])) {
    // Should return api_keys group with matching settings
    $foundOpenai = false;
    foreach ($r['body']['data'] as $grp) {
        if (isset($grp['settings'])) {
            foreach ($grp['settings'] as $s) {
                if (str_contains($s['key'] ?? '', 'openai')) $foundOpenai = true;
            }
        }
    }
    test('Found openai-related settings', $foundOpenai);
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 4) SHOW SINGLE SETTING (by ID)\n";
echo "══════════════════════════════════════════════\n";
// First get settings to find an ID
$r = req('GET', '/admin/settings?group=general');
$settingId = null;
$generalSettingKey = null;
if (isset($r['body']['data'])) {
    foreach ($r['body']['data'] as $grp) {
        if (isset($grp['settings'][0])) {
            $settingId = $grp['settings'][0]['id'];
            $generalSettingKey = $grp['settings'][0]['key'];
            break;
        }
    }
}
if ($settingId) {
    $r = req('GET', "/admin/settings/{$settingId}");
    test('Status 200', $r['code'] === 200);
    test("Returned correct setting ({$generalSettingKey})", ($r['body']['data']['key'] ?? '') === $generalSettingKey);
} else {
    test('Found setting ID for show test', false, 'Could not find general setting');
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 5) SHOW ENCRYPTED SETTING — MASKED VALUE\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings?group=api_keys');
$encId = null;
if (isset($r['body']['data'])) {
    foreach ($r['body']['data'] as $grp) {
        if (isset($grp['settings'])) {
            foreach ($grp['settings'] as $s) {
                if (($s['is_encrypted'] ?? false) && $s['key'] === 'openai_api_key') {
                    $encId = $s['id'];
                    test('Encrypted value is masked', str_contains($s['value'] ?? '', '••••'), $s['value'] ?? 'N/A');
                    test('No raw_value for encrypted', !isset($s['raw_value']));
                    break 2;
                }
            }
        }
    }
}
if (!$encId) test('Found encrypted setting', false);

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 6) CREATE NEW SETTING\n";
echo "══════════════════════════════════════════════\n";
$r = req('POST', '/admin/settings', [
    'group'        => 'general',
    'key'          => 'custom_test_setting',
    'value'        => 'hello_test',
    'type'         => 'string',
    'display_name' => 'Custom Test Setting',
    'description'  => 'A setting created during integration testing.',
    'is_public'    => true,
    'is_encrypted' => false,
]);
test('Status 201', $r['code'] === 201, "Got: {$r['code']}");
$newSettingId = $r['body']['data']['id'] ?? null;
test('Setting created with correct key', ($r['body']['data']['key'] ?? '') === 'custom_test_setting');
test('Setting value is correct', ($r['body']['data']['value'] ?? '') === 'hello_test');

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 7) CREATE SETTING — VALIDATION (duplicate key)\n";
echo "══════════════════════════════════════════════\n";
$r = req('POST', '/admin/settings', [
    'group' => 'general',
    'key'   => 'custom_test_setting',
    'value' => 'duplicate',
    'type'  => 'string',
]);
test('Status 422 (duplicate key)', $r['code'] === 422, "Got: {$r['code']}");

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 8) UPDATE SINGLE SETTING\n";
echo "══════════════════════════════════════════════\n";
if ($settingId) {
    $r = req('PUT', "/admin/settings/{$settingId}", ['value' => 'Flash AI Pro']);
    test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
    test('Value updated', ($r['body']['data']['value'] ?? '') === 'Flash AI Pro');
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 9) BULK UPDATE SETTINGS\n";
echo "══════════════════════════════════════════════\n";
$r = req('PUT', '/admin/settings/bulk', [
    'settings' => [
        ['key' => 'platform_name', 'value' => 'Flash AI Updated'],
        ['key' => 'items_per_page', 'value' => '20'],
        ['key' => 'theme_mode', 'value' => 'light'],
    ],
]);
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
// Verify
$r2 = req('GET', '/admin/settings?group=general');
if (isset($r2['body']['data'])) {
    $found = [];
    foreach ($r2['body']['data'] as $grp) {
        if (isset($grp['settings'])) {
            foreach ($grp['settings'] as $s) {
                if ($s['key'] === 'platform_name') $found['platform_name'] = $s['value'];
                if ($s['key'] === 'items_per_page') $found['items_per_page'] = $s['value'];
                if ($s['key'] === 'theme_mode') $found['theme_mode'] = $s['value'];
            }
        }
    }
    test('platform_name bulk-updated', ($found['platform_name'] ?? '') === 'Flash AI Updated', $found['platform_name'] ?? 'N/A');
    test('items_per_page bulk-updated', (string)($found['items_per_page'] ?? '') === '20', $found['items_per_page'] ?? 'N/A');
    test('theme_mode bulk-updated', ($found['theme_mode'] ?? '') === 'light', $found['theme_mode'] ?? 'N/A');
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 10) TOGGLE BOOLEAN SETTING\n";
echo "══════════════════════════════════════════════\n";
// Find a boolean feature setting
$r = req('GET', '/admin/settings?group=features');
$toggleId = null;
$toggleKey = null;
$originalVal = null;
if (isset($r['body']['data'])) {
    foreach ($r['body']['data'] as $grp) {
        if (isset($grp['settings'])) {
            foreach ($grp['settings'] as $s) {
                if ($s['key'] === 'social_login_enabled') {
                    $toggleId = $s['id'];
                    $toggleKey = $s['key'];
                    $originalVal = $s['value'];
                    break 2;
                }
            }
        }
    }
}
if ($toggleId) {
    $r = req('POST', "/admin/settings/{$toggleId}/toggle");
    test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
    $newVal = $r['body']['data']['value'] ?? null;
    test("Toggled {$toggleKey} (was {$originalVal}, now {$newVal})", $newVal != $originalVal, "new={$newVal}, old={$originalVal}");
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 11) TOGGLE NON-BOOLEAN SETTING (should fail)\n";
echo "══════════════════════════════════════════════\n";
if ($settingId) {
    $r = req('POST', "/admin/settings/{$settingId}/toggle");
    test('Status 422 (non-boolean toggle)', $r['code'] === 422, "Got: {$r['code']} — " . json_encode($r['body']['message'] ?? ''));
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 12) MAINTENANCE STATUS\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings/maintenance');
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
test('Has is_enabled key', isset($r['body']['data']['is_enabled']));
test('Has message key', isset($r['body']['data']['message']));
test('Maintenance off initially', ($r['body']['data']['is_enabled'] ?? true) === false);

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 13) TOGGLE MAINTENANCE ON\n";
echo "══════════════════════════════════════════════\n";
// toggleMaintenance is a simple toggle — no body params needed
$r = req('POST', '/admin/settings/maintenance/toggle');
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
test('Maintenance is now enabled', ($r['body']['data']['is_enabled'] ?? false) === true);

// Verify maintenance status
$r2 = req('GET', '/admin/settings/maintenance');
test('Confirmed enabled via status', ($r2['body']['data']['is_enabled'] ?? false) === true);

// Toggle back off
$r3 = req('POST', '/admin/settings/maintenance/toggle');
test('Toggled maintenance off', ($r3['body']['data']['is_enabled'] ?? true) === false);

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 14) RESET GROUP\n";
echo "══════════════════════════════════════════════\n";
$r = req('POST', '/admin/settings/reset/general');
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
// Verify platform_name is back to default
$r2 = req('GET', '/admin/settings?group=general');
if (isset($r2['body']['data'])) {
    foreach ($r2['body']['data'] as $grp) {
        if (isset($grp['settings'])) {
            foreach ($grp['settings'] as $s) {
                if ($s['key'] === 'platform_name') {
                    test('platform_name reset to default "Flash AI"', $s['value'] === 'Flash AI', "Got: {$s['value']}");
                    break 2;
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 15) TEST INTEGRATION (OpenAI — expected fail, demo key)\n";
echo "══════════════════════════════════════════════\n";
$r = req('POST', '/admin/settings/test-integration', ['integration' => 'openai']);
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
test('Has success field', isset($r['body']['data']['success']));
test('Has message field', isset($r['body']['data']['message']));
echo "  Integration result: " . ($r['body']['data']['success'] ? 'connected' : 'failed') . " — " . ($r['body']['data']['message'] ?? 'no message') . "\n";

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 16) TEST INTEGRATION — INVALID\n";
echo "══════════════════════════════════════════════\n";
$r = req('POST', '/admin/settings/test-integration', ['integration' => 'invalid_service']);
test('Status 422 (invalid integration)', $r['code'] === 422, "Got: {$r['code']}");

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 17) AUDIT LOG\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/admin/settings/audit-log');
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
test('Has data array', isset($r['body']['data']));
if (isset($r['body']['data']) && count($r['body']['data']) > 0) {
    $firstLog = $r['body']['data'][0];
    test('Log entry has action', isset($firstLog['action']));
    test('Log entry has metadata', isset($firstLog['metadata']));
    echo "  Total audit entries: " . ($r['body']['meta']['total'] ?? count($r['body']['data'])) . "\n";
    echo "  Latest action: " . ($firstLog['action'] ?? 'N/A') . "\n";
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 18) PUBLIC SETTINGS\n";
echo "══════════════════════════════════════════════\n";
$r = req('GET', '/settings/public');
test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
test('Has data array', isset($r['body']['data']));
if (isset($r['body']['data'])) {
    // publicSettings() returns key-value pairs like {"platform_name": "Flash AI", ...}
    $data = $r['body']['data'];
    test('Returns key-value pairs (not empty)', count((array)$data) > 0);
    test('Contains platform_name', isset($data['platform_name']));
    test('Contains theme_mode', isset($data['theme_mode']));
    // Encrypted keys should not appear
    test('No openai_api_key exposed', !isset($data['openai_api_key']));
    test('No stripe_secret_key exposed', !isset($data['stripe_secret_key']));
    echo "  Public settings count: " . count((array)$data) . "\n";
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 19) DELETE SETTING\n";
echo "══════════════════════════════════════════════\n";
if ($newSettingId) {
    $r = req('DELETE', "/admin/settings/{$newSettingId}");
    test('Status 200', $r['code'] === 200, "Got: {$r['code']}");
    
    // Verify deleted
    $r2 = req('GET', "/admin/settings/{$newSettingId}");
    test('Setting not found after delete', $r2['code'] === 404, "Got: {$r2['code']}");
}

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " 20) DELETE NON-EXISTENT SETTING\n";
echo "══════════════════════════════════════════════\n";
$r = req('DELETE', '/admin/settings/999999');
test('Status 404', $r['code'] === 404, "Got: {$r['code']}");

// ═══════════════════════════════════════════════
echo "\n══════════════════════════════════════════════\n";
echo " SUMMARY\n";
echo "══════════════════════════════════════════════\n";
echo "  Total: " . ($pass + $fail) . " | ✅ Passed: {$pass} | ❌ Failed: {$fail}\n";
echo "══════════════════════════════════════════════\n\n";
