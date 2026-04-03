<?php
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$ch = curl_init('http://127.0.0.1:8099/api/admin/plans/1');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer $token",'Accept: application/json']]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "detail feature keys: " . json_encode(array_keys($r['data']['features'][0])) . "\n";
echo "detail feature pivot keys: " . json_encode(array_keys($r['data']['features'][0]['pivot'])) . "\n";
echo "features_by_type[0]: " . json_encode($r['data']['features_by_type'][0], JSON_PRETTY_PRINT) . "\n";
echo "stats: " . json_encode($r['data']['stats'], JSON_PRETTY_PRINT) . "\n";
echo "recent_subscribers count: " . count($r['data']['recent_subscribers'] ?? []) . "\n";
if (!empty($r['data']['recent_subscribers'][0])) {
    echo "subscriber keys: " . json_encode(array_keys($r['data']['recent_subscribers'][0])) . "\n";
    echo "subscriber user keys: " . json_encode(array_keys($r['data']['recent_subscribers'][0]['user'])) . "\n";
}
