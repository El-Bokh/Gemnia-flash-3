<?php
// Verify Plans & Features API keys match frontend TypeScript interfaces
// Run: php verify_plans_keys.php

$baseUrl = 'http://127.0.0.1:8099/api';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';

function apiGet(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

$pass = 0;
$fail = 0;

function checkKeys(string $label, array $expected, array $actual): void {
    global $pass, $fail;
    $actualKeys = array_keys($actual);
    $missing = array_diff($expected, $actualKeys);
    $extra = array_diff($actualKeys, $expected);

    if (empty($missing) && empty($extra)) {
        echo "  ✅ $label — " . count($expected) . " keys match\n";
        $GLOBALS['pass']++;
    } else {
        if (!empty($missing)) {
            echo "  ❌ $label — MISSING: " . implode(', ', $missing) . "\n";
            $GLOBALS['fail']++;
        }
        if (!empty($extra)) {
            echo "  ⚠️  $label — EXTRA: " . implode(', ', $extra) . "\n";
        }
    }
}

echo "═══ Plans & Features API Key Verification ═══\n\n";

// 1. GET /admin/plans (list)
echo "── 1. GET /admin/plans (list) ──\n";
$res = apiGet("$baseUrl/admin/plans", $token);
if ($res['code'] === 200 && !empty($res['body']['data'])) {
    $plan = $res['body']['data'][0];
    checkKeys('Plan (list item)', [
        'id','name','slug','description','price_monthly','price_yearly','currency',
        'credits_monthly','credits_yearly','is_free','is_active','is_featured',
        'sort_order','trial_days','metadata','created_at','updated_at','deleted_at',
        'features','features_count','subscriptions_count','active_subscriptions_count',
    ], $plan);

    if (!empty($plan['features']) && count($plan['features']) > 0) {
        $feat = $plan['features'][0];
        checkKeys('PlanFeatureItem (flat)', ['id','name','slug','type','is_active','is_enabled','usage_limit','limit_period','credits_per_use','constraints'], $feat);
    } else {
        echo "  ⚠️  No features loaded in list — skipping feature check\n";
    }

    // Check pagination meta
    if (isset($res['body']['meta'])) {
        checkKeys('PaginatedResponse meta', ['current_page','last_page','per_page','total','from','to'], $res['body']['meta']);
    } else {
        echo "  ℹ️  No pagination meta — response is ApiResponse<Plan[]>\n";
    }
} else {
    echo "  ❌ Failed to fetch plans list (HTTP {$res['code']})\n";
    $fail++;
}

// 2. GET /admin/plans/{id} (detail)
echo "\n── 2. GET /admin/plans/{id} (detail) ──\n";
$planId = $res['body']['data'][0]['id'] ?? 1;
$res2 = apiGet("$baseUrl/admin/plans/$planId", $token);
if ($res2['code'] === 200 && !empty($res2['body']['data'])) {
    $detail = $res2['body']['data'];
    checkKeys('PlanDetail', [
        'id','name','slug','description','price_monthly','price_yearly','currency',
        'credits_monthly','credits_yearly','is_free','is_active','is_featured',
        'sort_order','trial_days','metadata','created_at','updated_at','deleted_at',
        'features','features_by_type','stats','recent_subscribers',
    ], $detail);

    // stats
    checkKeys('PlanDetailStats', ['total_features','enabled_features','total_subscriptions','active_subscriptions'], $detail['stats']);

    // features detail item
    if (!empty($detail['features'])) {
        $df = $detail['features'][0];
        checkKeys('PlanDetailFeatureItem', ['id','name','slug','description','type','is_active','sort_order','pivot'], $df);
        checkKeys('FeaturePivotWithId', ['id','is_enabled','usage_limit','limit_period','credits_per_use','constraints'], $df['pivot']);
    }

    // features_by_type
    if (!empty($detail['features_by_type'])) {
        $fbt = $detail['features_by_type'][0];
        checkKeys('PlanFeaturesByType', ['type','features'], $fbt);
        if (!empty($fbt['features'])) {
            checkKeys('PlanFeaturesByTypeItem', ['id','name','slug','is_enabled','usage_limit','limit_period'], $fbt['features'][0]);
        }
    }

    // recent_subscribers
    if (!empty($detail['recent_subscribers'])) {
        $sub = $detail['recent_subscribers'][0];
        checkKeys('PlanRecentSubscriber', ['id','user','billing_cycle','status','price','starts_at','ends_at','created_at'], $sub);
        checkKeys('PlanRecentSubscriberUser', ['id','name','email','avatar'], $sub['user']);
    } else {
        echo "  ⚠️  No recent_subscribers — skipping sub-check\n";
    }
} else {
    echo "  ❌ Failed to fetch plan detail (HTTP {$res2['code']})\n";
    $fail++;
}

// 3. GET /admin/plans/comparison
echo "\n── 3. GET /admin/plans/comparison ──\n";
$res3 = apiGet("$baseUrl/admin/plans/comparison", $token);
if ($res3['code'] === 200 && !empty($res3['body']['data'])) {
    $comp = $res3['body']['data'];
    checkKeys('ComparisonResponse', ['plans','features'], $comp);

    if (!empty($comp['plans'])) {
        $cp = $comp['plans'][0];
        checkKeys('ComparisonPlan', ['plan','features'], $cp);
        checkKeys('ComparisonPlanInfo', [
            'id','name','slug','price_monthly','price_yearly','credits_monthly',
            'is_free','is_featured','trial_days','active_subscribers',
        ], $cp['plan']);
        if (!empty($cp['features'])) {
            checkKeys('ComparisonFeatureItem', [
                'id','name','slug','type','included','is_enabled','usage_limit','limit_period','credits_per_use',
            ], $cp['features'][0]);
        }
    }

    if (!empty($comp['features'])) {
        checkKeys('ComparisonFeatureRef', ['id','name','slug','type'], $comp['features'][0]);
    }
} else {
    echo "  ❌ Failed to fetch comparison (HTTP {$res3['code']})\n";
    $fail++;
}

// 4. GET /admin/features (list)
echo "\n── 4. GET /admin/features (list) ──\n";
$res4 = apiGet("$baseUrl/admin/features", $token);
if ($res4['code'] === 200 && !empty($res4['body']['data'])) {
    $feat = $res4['body']['data'][0];
    checkKeys('Feature (list item)', [
        'id','name','slug','description','type','is_active','sort_order',
        'metadata','created_at','updated_at','plans','plans_count',
    ], $feat);

    if (!empty($feat['plans'])) {
        $fp = $feat['plans'][0];
        checkKeys('FeaturePlanItem (flat)', ['id','name','slug','is_active','is_enabled','usage_limit','limit_period','credits_per_use'], $fp);
    } else {
        echo "  ⚠️  No plans loaded in feature list — skipping plan check\n";
    }
} else {
    echo "  ❌ Failed to fetch features list (HTTP {$res4['code']})\n";
    $fail++;
}

// 5. GET /admin/features/{id} (detail)
echo "\n── 5. GET /admin/features/{id} (detail) ──\n";
$featId = $res4['body']['data'][0]['id'] ?? 1;
$res5 = apiGet("$baseUrl/admin/features/$featId", $token);
if ($res5['code'] === 200 && !empty($res5['body']['data'])) {
    $fd = $res5['body']['data'];
    checkKeys('Feature (detail)', [
        'id','name','slug','description','type','is_active','sort_order',
        'metadata','created_at','updated_at','plans','plans_count',
    ], $fd);
} else {
    echo "  ❌ Failed to fetch feature detail (HTTP {$res5['code']})\n";
    $fail++;
}

echo "\n═══════════════════════════════════════════\n";
echo "Results: ✅ $pass passed, ❌ $fail failed\n";
echo "═══════════════════════════════════════════\n";
