<?php
$ch = curl_init('http://127.0.0.1:8099/api/admin/plans');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer 4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91',
        'Accept: application/json',
    ],
]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "=== First Plan Feature (list) ===\n";
if (!empty($r['data'][0]['features'][0])) {
    echo json_encode($r['data'][0]['features'][0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No features in first plan\n";
}

echo "\n=== First Plan Detail Feature ===\n";
$planId = $r['data'][0]['id'] ?? 1;
$ch2 = curl_init("http://127.0.0.1:8099/api/admin/plans/$planId");
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer 4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91',
        'Accept: application/json',
    ],
]);
$r2 = json_decode(curl_exec($ch2), true);
curl_close($ch2);

if (!empty($r2['data']['features'][0])) {
    echo json_encode($r2['data']['features'][0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No features in detail\n";
}
