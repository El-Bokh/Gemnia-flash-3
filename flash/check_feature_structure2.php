<?php
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$base = 'http://127.0.0.1:8099/api';

function get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer $token",'Accept: application/json']]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $r;
}

echo "=== Feature List Item ===\n";
$r = get("$base/admin/features", $token);
if (!empty($r['data'][0])) echo json_encode($r['data'][0], JSON_PRETTY_PRINT) . "\n";

echo "\n=== Feature Detail ===\n";
$fid = $r['data'][0]['id'] ?? 1;
$r2 = get("$base/admin/features/$fid", $token);
if (!empty($r2['data'])) echo json_encode($r2['data'], JSON_PRETTY_PRINT) . "\n";

echo "\n=== Comparison (first plan) ===\n";
$r3 = get("$base/admin/plans/comparison", $token);
if (!empty($r3['data']['plans'][0])) {
    $cp = $r3['data']['plans'][0];
    echo "plan keys: " . json_encode(array_keys($cp['plan'])) . "\n";
    if (!empty($cp['features'][0])) echo "feature: " . json_encode($cp['features'][0], JSON_PRETTY_PRINT) . "\n";
}
if (!empty($r3['data']['features'][0])) {
    echo "feature ref: " . json_encode($r3['data']['features'][0], JSON_PRETTY_PRINT) . "\n";
}
