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

echo "Top-level keys: " . json_encode(array_keys($r)) . "\n";
if (isset($r['meta'])) {
    echo "Meta keys: " . json_encode(array_keys($r['meta'])) . "\n";
    echo "Meta: " . json_encode($r['meta'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No 'meta' key at top level.\n";
    // Check if pagination info is directly in the response
    foreach (['current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links'] as $k) {
        if (isset($r[$k])) echo "$k: " . json_encode($r[$k]) . "\n";
    }
}
