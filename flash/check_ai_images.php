<?php
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$base = 'http://127.0.0.1:8099/api';

$ch = curl_init("$base/admin/ai-requests?status=completed&has_images=1&per_page=1");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer $token",'Accept: application/json']]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!empty($r['data'][0])) {
    $id = $r['data'][0]['id'];
    $ch2 = curl_init("$base/admin/ai-requests/$id");
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer $token",'Accept: application/json']]);
    $r2 = json_decode(curl_exec($ch2), true);
    curl_close($ch2);

    $d = $r2['data'];
    if (!empty($d['generated_images'])) {
        echo "GeneratedImage keys: " . json_encode(array_keys($d['generated_images'][0])) . "\n";
        echo "GeneratedImage sample: " . json_encode($d['generated_images'][0], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No generated_images even for completed request $id\n";
    }
    if (!empty($d['usage_logs'])) {
        echo "\nUsageLog keys: " . json_encode(array_keys($d['usage_logs'][0])) . "\n";
        echo "UsageLog sample: " . json_encode($d['usage_logs'][0], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No usage_logs for request $id\n";
    }
} else {
    echo "No completed AI requests with images found.\n";
}
