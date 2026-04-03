<?php
$ch = curl_init('http://127.0.0.1:8099/api/admin/payments/1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer 4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91',
        'Accept: application/json',
    ],
]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "Payment detail user keys:\n";
print_r(array_keys($r['data']['user'] ?? []));

// Check coupon detail
$ch = curl_init('http://127.0.0.1:8099/api/admin/coupons?per_page=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer 4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91',
        'Accept: application/json',
    ],
]);
$list = json_decode(curl_exec($ch), true);
curl_close($ch);
$cid = $list['data'][0]['id'] ?? null;

if ($cid) {
    $ch = curl_init("http://127.0.0.1:8099/api/admin/coupons/{$cid}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer 4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91',
            'Accept: application/json',
        ],
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    echo "\nCoupon detail keys:\n";
    print_r(array_keys($r['data'] ?? []));
}
