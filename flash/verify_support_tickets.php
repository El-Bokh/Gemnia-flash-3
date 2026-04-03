<?php
/**
 * Verify Support Tickets frontend types match backend API responses.
 * Run: php verify_support_tickets.php
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

echo "\n═══ SUPPORT TICKETS ═══\n";

// 1) List tickets
$r = api('GET', '/support-tickets?per_page=2');
check('List tickets 200', $r['code'] === 200);
check('Pagination meta', isset($r['body']['meta']['current_page']));

$ticketId = null;
if (!empty($r['body']['data'])) {
    $t = $r['body']['data'][0];
    check('Ticket list keys', hasKeys($t, [
        'id','uuid','ticket_number','subject','message_preview','status',
        'priority','category','replies_count','last_reply_at','resolved_at',
        'closed_at','created_at','updated_at','deleted_at','user','assigned_agent'
    ]));
    check('Ticket user keys', hasKeys($t['user'] ?? [], ['id','name','email','avatar']));
    check('Has user_subscription key', array_key_exists('user_subscription', $t));
    $ticketId = $t['id'];
} else {
    echo "  ⚠️  No tickets in DB, skipping detail checks\n";
}

// 2) Ticket detail
if ($ticketId) {
    $r = api('GET', "/support-tickets/{$ticketId}");
    check('Ticket detail 200', $r['code'] === 200);
    $d = $r['body']['data'] ?? [];
    check('TicketDetail keys', hasKeys($d, [
        'id','uuid','ticket_number','subject','message','status',
        'priority','category','attachments','metadata','last_reply_at',
        'resolved_at','closed_at','created_at','updated_at','deleted_at',
        'replies_count','user','assigned_agent','replies'
    ]));
    check('Detail user keys', hasKeys($d['user'] ?? [], [
        'id','name','email','avatar','phone','status'
    ]));
    check('Detail user has subscription key', array_key_exists('subscription', $d['user'] ?? []));
    check('Replies is array', is_array($d['replies'] ?? null));

    if (!empty($d['replies'])) {
        $reply = $d['replies'][0];
        check('Reply keys', hasKeys($reply, [
            'id','message','is_staff_reply','attachments','created_at','updated_at','user'
        ]));
        check('Reply user keys', hasKeys($reply['user'] ?? [], ['id','name','email','avatar']));
    }
}

// 3) Update ticket
if ($ticketId) {
    $r = api('PUT', "/support-tickets/{$ticketId}", ['priority' => 'high']);
    check('Update ticket 200', $r['code'] === 200);
    check('Update returns ticket', isset($r['body']['data']['id']));
}

// 4) Reply to ticket
if ($ticketId) {
    $r = api('POST', "/support-tickets/{$ticketId}/reply", [
        'message' => 'Test admin reply from verification script.',
    ]);
    check('Reply to ticket 201', $r['code'] === 201);
    $rp = $r['body']['data'] ?? [];
    check('Reply response keys', hasKeys($rp, [
        'id','message','is_staff_reply','attachments','created_at','updated_at','user'
    ]));
    check('Reply is_staff_reply', ($rp['is_staff_reply'] ?? false) === true);
}

// 5) Assign ticket (assign to admin user id=1)
if ($ticketId) {
    $r = api('POST', "/support-tickets/{$ticketId}/assign", ['assigned_to' => 1]);
    check('Assign ticket 200', $r['code'] === 200);
    check('Assign returns ticket', isset($r['body']['data']['id']));
    check('Assigned agent present', isset($r['body']['data']['assigned_agent']));
}

// 6) Close ticket
if ($ticketId) {
    $r = api('POST', "/support-tickets/{$ticketId}/close");
    // May fail if already closed, accept both 200 and 422
    if ($r['code'] === 200) {
        check('Close ticket 200', true);
        check('Close returns ticket', isset($r['body']['data']['status']));
    } else {
        check('Close ticket (already closed, 422)', $r['code'] === 422);
    }
}

// 7) Reopen ticket
if ($ticketId) {
    $r = api('POST', "/support-tickets/{$ticketId}/reopen");
    if ($r['code'] === 200) {
        check('Reopen ticket 200', true);
    } else {
        check('Reopen ticket (not closed/resolved, 422)', $r['code'] === 422);
    }
}

// 8) Aggregations
$r = api('GET', '/support-tickets/aggregations');
check('Aggregations 200', $r['code'] === 200);
$agg = $r['body']['data'] ?? [];
check('Aggregation keys', hasKeys($agg, [
    'summary','by_priority','by_category','agent_performance','daily_trend','avg_first_response'
]));
check('Summary keys', hasKeys($agg['summary'] ?? [], [
    'total_tickets','open_count','in_progress_count','waiting_reply_count',
    'resolved_count','closed_count','unassigned_active_count'
]));
check('Avg first response key', array_key_exists('avg_first_response_minutes', $agg['avg_first_response'] ?? []));

if (!empty($agg['by_priority'])) {
    check('By priority item keys', hasKeys($agg['by_priority'][0], ['priority','count','active_count']));
}

if (!empty($agg['daily_trend'])) {
    check('Daily trend item keys', hasKeys($agg['daily_trend'][0], ['date','created','resolved']));
}

if (!empty($agg['agent_performance'])) {
    check('Agent perf item keys', hasKeys($agg['agent_performance'][0], [
        'id','name','email','total_assigned','resolved_count','active_count','avg_resolution_hours'
    ]));
}

// ════════════════════════════════════════════════
//  RESULTS
// ════════════════════════════════════════════════
echo "\n════════════════════════════════════\n";
echo "  ✅ Passed: {$pass}\n";
echo "  ❌ Failed: {$fail}\n";
echo "  Total:    " . ($pass + $fail) . "\n";
echo "════════════════════════════════════\n";
exit($fail > 0 ? 1 : 0);
