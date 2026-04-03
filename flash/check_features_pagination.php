<?php
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';

$ch = curl_init('http://127.0.0.1:8099/api/admin/features');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer $token",'Accept: application/json']]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "Features top-level keys: " . json_encode(array_keys($r)) . "\n";
echo "Data count: " . count($r['data'] ?? []) . "\n";
if (isset($r['meta'])) echo "Meta: " . json_encode($r['meta'], JSON_PRETTY_PRINT) . "\n";
if (isset($r['links'])) echo "Links: " . json_encode($r['links'], JSON_PRETTY_PRINT) . "\n";
