<?php
/**
 * Verify Payments & Billing frontend types match backend API responses.
 * Run: php verify_payments.php
 */

$base  = 'http://127.0.0.1:8099/api/admin';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';
$pass  = 0;
$fail  = 0;

function api(string $method, string $url, array $body = []): array {
    global $base, $token;
    $ch = curl_init("{$base}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true) ?? []];
}

function check(string $label, bool $condition): void {
    global $pass, $fail;
    if ($condition) { $pass++; echo "  ✅ {$label}\n"; }
    else           { $fail++; echo "  ❌ {$label}\n"; }
}

function hasKeys(array $data, array $keys): bool {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $data)) return false;
    }
    return true;
}

// ════════════════════════════════════════════════
//  PAYMENTS
// ════════════════════════════════════════════════
echo "\n═══ PAYMENTS ═══\n";

// 1) List payments
$r = api('GET', '/payments?per_page=2');
check('List payments 200', $r['code'] === 200);
if (!empty($r['body']['data'])) {
    $p = $r['body']['data'][0];
    check('Payment list keys', hasKeys($p, [
        'id','uuid','payment_gateway','gateway_payment_id','status','amount',
        'discount_amount','tax_amount','net_amount','currency','payment_method',
        'description','refunded_amount','refunded_at','paid_at','created_at',
        'updated_at','deleted_at','user','invoices_count'
    ]));
    check('Payment user keys', hasKeys($p['user'] ?? [], ['id','name','email','avatar']));
    check('Pagination meta', isset($r['body']['meta']['current_page']));
    $paymentId = $p['id'];
} else {
    echo "  ⚠️  No payments in DB, skipping detail checks\n";
    $paymentId = null;
}

// 2) Payment detail
if ($paymentId) {
    $r = api('GET', "/payments/{$paymentId}");
    check('Payment detail 200', $r['code'] === 200);
    $d = $r['body']['data'] ?? [];
    check('PaymentDetail keys', hasKeys($d, [
        'id','uuid','payment_gateway','gateway_payment_id','gateway_customer_id',
        'status','amount','discount_amount','tax_amount','net_amount','currency',
        'payment_method','description','refunded_amount','refunded_at','refund_reason',
        'billing_name','billing_email','billing_address','billing_city','billing_state',
        'billing_zip','billing_country','gateway_response','metadata','paid_at',
        'created_at','updated_at','deleted_at','user','invoices'
    ]));
    check('PaymentDetail user has status', array_key_exists('status', $d['user'] ?? []));
}

// 3) Payment aggregations
$r = api('GET', '/payments/aggregations');
check('Payment aggregations 200', $r['code'] === 200);
$agg = $r['body']['data'] ?? [];
check('Payment agg keys', hasKeys($agg, [
    'summary','by_gateway','by_currency','daily_trend','top_users','status_distribution'
]));
check('Payment agg summary keys', hasKeys($agg['summary'] ?? [], [
    'total_payments','total_revenue','total_net_revenue','total_discounts','total_taxes',
    'total_refunded','avg_payment_amount','completed_count','failed_count','pending_count',
    'refunded_count','disputed_count'
]));

// ════════════════════════════════════════════════
//  INVOICES
// ════════════════════════════════════════════════
echo "\n═══ INVOICES ═══\n";

// 4) List invoices
$r = api('GET', '/invoices?per_page=2');
check('List invoices 200', $r['code'] === 200);
if (!empty($r['body']['data'])) {
    $inv = $r['body']['data'][0];
    check('Invoice list keys', hasKeys($inv, [
        'id','uuid','invoice_number','status','subtotal','discount_amount',
        'tax_amount','total','currency','issued_at','due_at','paid_at',
        'created_at','deleted_at','user'
    ]));
    check('Invoice user keys', hasKeys($inv['user'] ?? [], ['id','name','email']));
    $invoiceId = $inv['id'];
} else {
    echo "  ⚠️  No invoices in DB, skipping detail checks\n";
    $invoiceId = null;
}

// 5) Invoice detail
if ($invoiceId) {
    $r = api('GET', "/invoices/{$invoiceId}");
    check('Invoice detail 200', $r['code'] === 200);
    $d = $r['body']['data'] ?? [];
    check('InvoiceDetail keys', hasKeys($d, [
        'id','uuid','invoice_number','status','subtotal','discount_amount',
        'tax_amount','total','currency','billing_name','billing_email',
        'billing_address','billing_city','billing_state','billing_zip',
        'billing_country','line_items','notes','footer','metadata',
        'issued_at','due_at','paid_at','created_at','updated_at','deleted_at','user'
    ]));

    // 6) Invoice download
    $r = api('GET', "/invoices/{$invoiceId}/download");
    check('Invoice download 200', $r['code'] === 200);
    $dl = $r['body']['data'] ?? [];
    check('Download data keys', hasKeys($dl, ['invoice','company']));
    check('Download company keys', hasKeys($dl['company'] ?? [], ['name','email']));
}

// 7) Invoice aggregations
$r = api('GET', '/invoices/aggregations');
check('Invoice aggregations 200', $r['code'] === 200);
$agg = $r['body']['data'] ?? [];
check('Invoice agg keys', hasKeys($agg, ['summary','overdue']));
check('Invoice agg summary keys', hasKeys($agg['summary'] ?? [], [
    'total_invoices','total_paid','paid_count','draft_count','issued_count',
    'overdue_count','cancelled_count','refunded_count','avg_invoice_total'
]));

// ════════════════════════════════════════════════
//  COUPONS
// ════════════════════════════════════════════════
echo "\n═══ COUPONS ═══\n";

// 8) List coupons
$r = api('GET', '/coupons?per_page=2');
check('List coupons 200', $r['code'] === 200);
if (!empty($r['body']['data'])) {
    $c = $r['body']['data'][0];
    check('Coupon list keys', hasKeys($c, [
        'id','code','name','discount_type','discount_value','currency',
        'is_active','max_uses','max_uses_per_user','times_used',
        'usage_percentage','starts_at','expires_at','is_expired',
        'created_at','deleted_at'
    ]));
    $couponId = $c['id'];
} else {
    echo "  ⚠️  No coupons in DB — creating test coupon\n";
    $r = api('POST', '/coupons', [
        'code' => 'TESTVERIFY' . rand(100,999),
        'name' => 'Test Verify Coupon',
        'discount_type' => 'percentage',
        'discount_value' => 10,
    ]);
    check('Create coupon 201', $r['code'] === 201);
    $couponId = $r['body']['data']['id'] ?? null;
}

// 9) Coupon detail
if ($couponId) {
    $r = api('GET', "/coupons/{$couponId}");
    check('Coupon detail 200', $r['code'] === 200);
    $d = $r['body']['data'] ?? [];
    check('CouponDetail keys', hasKeys($d, [
        'id','code','name','discount_type','discount_value','currency',
        'is_active','max_uses','max_uses_per_user','times_used',
        'usage_percentage','starts_at','expires_at','is_expired',
        'description','min_order_amount','metadata','updated_at',
        'payments_count'
    ]));

    // 10) Coupon usage
    $r = api('GET', "/coupons/{$couponId}/usage");
    check('Coupon usage 200', $r['code'] === 200);
    $u = $r['body']['data'] ?? [];
    check('Coupon usage keys', hasKeys($u, ['summary','user_breakdown','daily_trend']));
    check('Coupon usage summary keys', hasKeys($u['summary'] ?? [], [
        'total_uses','total_discount_given','total_revenue','unique_users','avg_discount'
    ]));

    // 11) Toggle coupon
    $r = api('POST', "/coupons/{$couponId}/toggle");
    check('Toggle coupon 200', $r['code'] === 200);
}

// 12) Validate coupon (needs a valid code)
if ($couponId) {
    $r = api('GET', "/coupons/{$couponId}");
    $code = $r['body']['data']['code'] ?? 'NONE';
    $r = api('POST', '/coupons/validate', ['code' => $code]);
    check('Validate coupon 200', $r['code'] === 200);
    $v = $r['body']['data'] ?? [];
    check('Validate response has valid', array_key_exists('valid', $v));
    if ($v['valid'] ?? false) {
        check('Validate coupon keys', hasKeys($v['coupon'] ?? [], [
            'id','code','name','discount_type','discount_value','currency'
        ]));
    }
}

// 13) Coupon aggregations
$r = api('GET', '/coupons/aggregations');
check('Coupon aggregations 200', $r['code'] === 200);
$agg = $r['body']['data'] ?? [];
check('Coupon agg keys', hasKeys($agg, ['summary','expired_count','by_type','top_coupons']));
check('Coupon agg summary keys', hasKeys($agg['summary'] ?? [], [
    'total_coupons','active_count','inactive_count','total_uses'
]));

// ════════════════════════════════════════════════
//  RESULTS
// ════════════════════════════════════════════════
echo "\n════════════════════════════════════\n";
echo "  ✅ Passed: {$pass}\n";
echo "  ❌ Failed: {$fail}\n";
echo "  Total:    " . ($pass + $fail) . "\n";
echo "════════════════════════════════════\n";
exit($fail > 0 ? 1 : 0);
