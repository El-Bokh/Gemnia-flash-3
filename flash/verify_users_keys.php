<?php
$base  = 'http://127.0.0.1:8099/api';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';

function req(string $url): array {
    global $base, $token;
    $ch = curl_init("{$base}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Accept: application/json'],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $resp;
}

// 1) LIST
echo "=== GET /admin/users (top-level keys) ===" . PHP_EOL;
$r = req('/admin/users?per_page=2');
echo implode(', ', array_keys($r)) . PHP_EOL;

echo PHP_EOL . "=== User list item keys ===" . PHP_EOL;
if (!empty($r['data'])) {
    echo implode(', ', array_keys($r['data'][0])) . PHP_EOL;
    if (!empty($r['data'][0]['active_subscription'])) {
        echo PHP_EOL . "=== active_subscription keys ===" . PHP_EOL;
        echo implode(', ', array_keys($r['data'][0]['active_subscription'])) . PHP_EOL;
    }
}

echo PHP_EOL . "=== meta keys ===" . PHP_EOL;
echo implode(', ', array_keys($r['meta'])) . PHP_EOL;

echo PHP_EOL . "=== links keys ===" . PHP_EOL;
echo implode(', ', array_keys($r['links'])) . PHP_EOL;

// 2) SHOW (user 1)
echo PHP_EOL . "=== GET /admin/users/1 (detail top keys) ===" . PHP_EOL;
$r = req('/admin/users/1');
echo implode(', ', array_keys($r['data'])) . PHP_EOL;

echo PHP_EOL . "=== detail > stats keys ===" . PHP_EOL;
echo implode(', ', array_keys($r['data']['stats'])) . PHP_EOL;

if (!empty($r['data']['subscriptions'])) {
    echo PHP_EOL . "=== detail > subscriptions[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['subscriptions'][0])) . PHP_EOL;
}

if (!empty($r['data']['recent_ai_requests'])) {
    echo PHP_EOL . "=== detail > recent_ai_requests[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['recent_ai_requests'][0])) . PHP_EOL;
}

if (!empty($r['data']['recent_generated_images'])) {
    echo PHP_EOL . "=== detail > recent_generated_images[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['recent_generated_images'][0])) . PHP_EOL;
}

if (!empty($r['data']['recent_payments'])) {
    echo PHP_EOL . "=== detail > recent_payments[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['recent_payments'][0])) . PHP_EOL;
}

if (!empty($r['data']['credit_ledger'])) {
    echo PHP_EOL . "=== detail > credit_ledger keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['credit_ledger'])) . PHP_EOL;
    if (!empty($r['data']['credit_ledger']['recent'])) {
        echo PHP_EOL . "=== credit_ledger > recent[0] keys ===" . PHP_EOL;
        echo implode(', ', array_keys($r['data']['credit_ledger']['recent'][0])) . PHP_EOL;
    }
}

// 3) AGGREGATIONS
echo PHP_EOL . "=== GET /admin/users/aggregations ===" . PHP_EOL;
$r = req('/admin/users/aggregations');
echo implode(', ', array_keys($r['data'])) . PHP_EOL;

if (!empty($r['data']['users_per_role'])) {
    echo PHP_EOL . "=== users_per_role[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['users_per_role'][0])) . PHP_EOL;
}

if (!empty($r['data']['users_per_plan'])) {
    echo PHP_EOL . "=== users_per_plan[0] keys ===" . PHP_EOL;
    echo implode(', ', array_keys($r['data']['users_per_plan'][0])) . PHP_EOL;
}

// 4) AI REQUESTS sub-resource
echo PHP_EOL . "=== GET /admin/users/1/ai-requests ===" . PHP_EOL;
$r = req('/admin/users/1/ai-requests?per_page=2');
echo "top keys: " . implode(', ', array_keys($r)) . PHP_EOL;
if (!empty($r['data'])) {
    echo "item keys: " . implode(', ', array_keys($r['data'][0])) . PHP_EOL;
}
echo "meta keys: " . implode(', ', array_keys($r['meta'])) . PHP_EOL;

// 5) GENERATED IMAGES sub-resource
echo PHP_EOL . "=== GET /admin/users/1/generated-images ===" . PHP_EOL;
$r = req('/admin/users/1/generated-images?per_page=2');
echo "top keys: " . implode(', ', array_keys($r)) . PHP_EOL;
if (!empty($r['data'])) {
    echo "item keys: " . implode(', ', array_keys($r['data'][0])) . PHP_EOL;
}
echo "meta keys: " . implode(', ', array_keys($r['meta'])) . PHP_EOL;

echo PHP_EOL . "✅ ALL USER KEYS VERIFIED" . PHP_EOL;
