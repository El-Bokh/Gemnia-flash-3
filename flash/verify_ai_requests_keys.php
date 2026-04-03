<?php
// Verify AI Requests API keys match frontend TypeScript interfaces
// Run: php verify_ai_requests_keys.php

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

    if (empty($missing)) {
        echo "  ✅ $label — " . count($expected) . "/" . count($actualKeys) . " keys match";
        if (!empty($extra)) {
            echo " (extra: " . implode(', ', $extra) . ")";
        }
        echo "\n";
        $pass++;
    } else {
        echo "  ❌ $label — MISSING: " . implode(', ', $missing) . "\n";
        if (!empty($extra)) {
            echo "     EXTRA: " . implode(', ', $extra) . "\n";
        }
        $fail++;
    }
}

echo "═══ AI Requests API Key Verification ═══\n\n";

// 1. GET /admin/ai-requests (list with pagination)
echo "── 1. GET /admin/ai-requests (list) ──\n";
$res = apiGet("$baseUrl/admin/ai-requests?per_page=5", $token);
if ($res['code'] === 200 && !empty($res['body']['data'])) {
    $item = $res['body']['data'][0];

    // AiRequest (list item) — error_message/error_code are conditional (only when failed)
    $baseKeys = [
        'id','uuid','type','status','user_prompt','model_used','engine_provider',
        'width','height','num_images','credits_consumed','retry_count','processing_time_ms',
        'started_at','completed_at','created_at','updated_at','deleted_at',
        'user','visual_style','generated_images_count',
    ];
    checkKeys('AiRequest (list item)', $baseKeys, $item);

    // User ref
    if (!empty($item['user'])) {
        checkKeys('AiRequestUserRef', ['id','name','email','avatar'], $item['user']);
    }

    // Visual style ref
    if ($item['visual_style'] !== null) {
        checkKeys('AiRequestVisualStyleRef', ['id','name','slug','thumbnail'], $item['visual_style']);
    } else {
        echo "  ℹ️  visual_style is null — skipping sub-check\n";
    }

    // Meta (pagination)
    if (!empty($res['body']['meta'])) {
        checkKeys('PaginatedResponse meta', ['current_page','last_page','per_page','total','from','to'], $res['body']['meta']);
    }
} else {
    echo "  ❌ Failed to fetch AI requests list (HTTP {$res['code']})\n";
    $fail++;
}

// 2. GET /admin/ai-requests/{id} (detail)
echo "\n── 2. GET /admin/ai-requests/{id} (detail) ──\n";
$aiRequestId = $res['body']['data'][0]['id'] ?? 1;
$res2 = apiGet("$baseUrl/admin/ai-requests/$aiRequestId", $token);
if ($res2['code'] === 200 && !empty($res2['body']['data'])) {
    $detail = $res2['body']['data'];

    checkKeys('AiRequestDetail', [
        'id','uuid','type','status',
        'user_prompt','processed_prompt','negative_prompt','hidden_prompt',
        'model_used','engine_provider',
        'width','height','steps','cfg_scale','sampler','seed','num_images','denoising_strength',
        'input_image_path','mask_image_path',
        'credits_consumed','processing_time_ms',
        'error_message','error_code','retry_count',
        'ip_address','user_agent',
        'request_payload','response_payload','metadata',
        'started_at','completed_at','created_at','updated_at','deleted_at',
        'user','subscription','visual_style','generated_images','usage_logs','stats',
    ], $detail);

    // User detail ref
    if (!empty($detail['user'])) {
        checkKeys('AiRequestUserDetailRef', ['id','name','email','avatar','status'], $detail['user']);
    }

    // Subscription ref
    if ($detail['subscription'] !== null) {
        checkKeys('AiRequestSubscriptionRef', ['id','status','plan'], $detail['subscription']);
        if (!empty($detail['subscription']['plan'])) {
            checkKeys('SubscriptionPlanRef', ['id','name','slug'], $detail['subscription']['plan']);
        }
    } else {
        echo "  ℹ️  subscription is null — skipping sub-check\n";
    }

    // Visual style detail ref
    if ($detail['visual_style'] !== null) {
        checkKeys('AiRequestVisualStyleDetailRef', ['id','name','slug','thumbnail','category','prompt_prefix','prompt_suffix'], $detail['visual_style']);
    } else {
        echo "  ℹ️  visual_style is null — skipping sub-check\n";
    }

    // Generated images
    if (!empty($detail['generated_images'])) {
        checkKeys('AiRequestGeneratedImage', [
            'id','uuid','file_path','file_name','disk','mime_type','file_size',
            'width','height','thumbnail_path','is_public','is_nsfw',
            'download_count','view_count','created_at',
        ], $detail['generated_images'][0]);
    } else {
        echo "  ℹ️  No generated_images — skipping sub-check\n";
    }

    // Usage logs
    if (!empty($detail['usage_logs'])) {
        checkKeys('AiRequestUsageLog', ['id','action','credits_used','feature','created_at'], $detail['usage_logs'][0]);
    } else {
        echo "  ℹ️  No usage_logs — skipping sub-check\n";
    }

    // Stats
    checkKeys('AiRequestDetailStats', ['generated_images_count','usage_logs_count','total_credits_logged'], $detail['stats']);
} else {
    echo "  ❌ Failed to fetch AI request detail (HTTP {$res2['code']})\n";
    $fail++;
}

// 3. GET /admin/ai-requests/aggregations
echo "\n── 3. GET /admin/ai-requests/aggregations ──\n";
$res3 = apiGet("$baseUrl/admin/ai-requests/aggregations", $token);
if ($res3['code'] === 200 && !empty($res3['body']['data'])) {
    $agg = $res3['body']['data'];

    checkKeys('AiRequestAggregations', [
        'overview','by_status','by_type','by_engine','by_model',
        'daily_trend','top_users','top_visual_styles','error_codes',
    ], $agg);

    checkKeys('AiRequestAggregationsOverview', [
        'total_requests','total_credits_consumed','total_images_requested',
        'avg_processing_time_ms','max_processing_time_ms','min_processing_time_ms',
        'avg_credits_per_request','avg_retry_count','requests_with_retries',
        'success_rate','failure_rate',
    ], $agg['overview']);

    // Daily trend item
    if (!empty($agg['daily_trend'])) {
        $dayItem = is_array($agg['daily_trend'][0]) ? $agg['daily_trend'][0] : (array) $agg['daily_trend'][0];
        checkKeys('AiRequestDailyTrend', ['date','total','completed','failed','credits'], $dayItem);
    } else {
        echo "  ℹ️  No daily_trend data — skipping sub-check\n";
    }

    // Top users item
    if (!empty($agg['top_users'])) {
        $topUser = is_array($agg['top_users'][0]) ? $agg['top_users'][0] : (array) $agg['top_users'][0];
        checkKeys('AiRequestTopUser', ['user','request_count','total_credits','completed_count'], $topUser);
        if (!empty($topUser['user'])) {
            checkKeys('AiRequestTopUser.user', ['id','name','email','avatar'], $topUser['user']);
        }
    } else {
        echo "  ℹ️  No top_users data — skipping sub-check\n";
    }

    // Top visual styles item
    if (!empty($agg['top_visual_styles'])) {
        $topStyle = is_array($agg['top_visual_styles'][0]) ? $agg['top_visual_styles'][0] : (array) $agg['top_visual_styles'][0];
        checkKeys('AiRequestTopStyle', ['style','usage_count'], $topStyle);
        if (!empty($topStyle['style'])) {
            checkKeys('AiRequestTopStyleRef', ['id','name','slug','thumbnail'], $topStyle['style']);
        }
    } else {
        echo "  ℹ️  No top_visual_styles data — skipping sub-check\n";
    }

    // Error codes item
    if (!empty($agg['error_codes'])) {
        $errItem = is_array($agg['error_codes'][0]) ? $agg['error_codes'][0] : (array) $agg['error_codes'][0];
        checkKeys('AiRequestErrorCode', ['error_code','count'], $errItem);
    } else {
        echo "  ℹ️  No error_codes data — skipping sub-check\n";
    }
} else {
    echo "  ❌ Failed to fetch aggregations (HTTP {$res3['code']})\n";
    $fail++;
}

echo "\n═══════════════════════════════════════════\n";
echo "Results: ✅ $pass passed, ❌ $fail failed\n";
echo "═══════════════════════════════════════════\n";
