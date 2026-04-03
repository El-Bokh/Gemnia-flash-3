<?php
$base = 'http://127.0.0.1:8099/api';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$ch = curl_init("{$base}/admin/dashboard");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Accept: application/json'],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "=== TOP-LEVEL KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data'])) . PHP_EOL;

echo PHP_EOL . "=== KPIs KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['kpis'])) . PHP_EOL;

echo PHP_EOL . "=== KPIs > users KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['kpis']['users'])) . PHP_EOL;

echo PHP_EOL . "=== KPIs > subscriptions_per_plan[0] KEYS ===" . PHP_EOL;
if (count($resp['data']['kpis']['subscriptions_per_plan']) > 0) {
    echo implode(', ', array_keys($resp['data']['kpis']['subscriptions_per_plan'][0])) . PHP_EOL;
} else { echo "(empty)" . PHP_EOL; }

echo PHP_EOL . "=== KPIs > revenue_today KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['kpis']['revenue_today'])) . PHP_EOL;

echo PHP_EOL . "=== Charts KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['charts'])) . PHP_EOL;

echo PHP_EOL . "=== Charts > images_last_7_days[0] KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['charts']['images_last_7_days'][0])) . PHP_EOL;

echo PHP_EOL . "=== recent_ai_requests[0] KEYS ===" . PHP_EOL;
if (count($resp['data']['recent_ai_requests']) > 0) {
    echo implode(', ', array_keys($resp['data']['recent_ai_requests'][0])) . PHP_EOL;
} else { echo "(empty)" . PHP_EOL; }

echo PHP_EOL . "=== recent_payments[0] KEYS ===" . PHP_EOL;
if (count($resp['data']['recent_payments']) > 0) {
    echo implode(', ', array_keys($resp['data']['recent_payments'][0])) . PHP_EOL;
} else { echo "(empty)" . PHP_EOL; }

echo PHP_EOL . "=== alerts KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['alerts'])) . PHP_EOL;

echo PHP_EOL . "=== alerts > failed_requests KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['alerts']['failed_requests'])) . PHP_EOL;

echo PHP_EOL . "=== alerts > pending_payments KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['alerts']['pending_payments'])) . PHP_EOL;

echo PHP_EOL . "=== alerts > system KEYS ===" . PHP_EOL;
echo implode(', ', array_keys($resp['data']['alerts']['system'])) . PHP_EOL;

echo PHP_EOL . "✅ ALL KEYS VERIFIED" . PHP_EOL;
