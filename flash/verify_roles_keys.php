<?php
$base  = 'http://127.0.0.1:8099/api';
$token = '4|vxxOTsOG2uvZvTi2J8FN3n8sJ6Gt5uREjqX0x2s3c1776a91';

function req(string $url): array {
    global $base, $token;
    $ch = curl_init("{$base}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Accept: application/json'],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $resp;
}

$pass = 0; $fail = 0;
function check(string $label, array $expected, array $actual): void {
    global $pass, $fail;
    $missing = array_diff($expected, $actual);
    if (empty($missing)) {
        echo "  ✅ {$label}: " . implode(', ', $actual) . PHP_EOL;
        $pass++;
    } else {
        echo "  ❌ {$label} — missing: " . implode(', ', $missing) . PHP_EOL;
        echo "     Got: " . implode(', ', $actual) . PHP_EOL;
        $fail++;
    }
}

// 1) LIST ROLES
echo "=== GET /admin/roles ===" . PHP_EOL;
$r = req('/admin/roles?with_counts=1');
check('Role list item keys',
    ['id','name','slug','description','is_default','created_at','updated_at','permissions','permissions_count','users_count'],
    array_keys($r['data'][0])
);
if (!empty($r['data'][0]['permissions'])) {
    check('Role > permissions[0] keys',
        ['id','name','slug','group'],
        array_keys($r['data'][0]['permissions'][0])
    );
}

// 2) SHOW ROLE DETAIL
echo PHP_EOL . "=== GET /admin/roles/1 ===" . PHP_EOL;
$r = req('/admin/roles/1');
check('RoleDetail keys',
    ['id','name','slug','description','is_default','created_at','updated_at','permissions','permissions_grouped','permissions_count','users_count','recent_users'],
    array_keys($r['data'])
);
if (!empty($r['data']['permissions'])) {
    check('RoleDetail > permissions[0] keys',
        ['id','name','slug','group','description'],
        array_keys($r['data']['permissions'][0])
    );
}
if (!empty($r['data']['permissions_grouped'])) {
    check('RoleDetail > permissions_grouped[0] keys',
        ['group','permissions'],
        array_keys($r['data']['permissions_grouped'][0])
    );
}
if (!empty($r['data']['recent_users'])) {
    check('RoleDetail > recent_users[0] keys',
        ['id','name','email','avatar','status'],
        array_keys($r['data']['recent_users'][0])
    );
}

// 3) PERMISSION MATRIX
echo PHP_EOL . "=== GET /admin/roles/matrix ===" . PHP_EOL;
$r = req('/admin/roles/matrix');
check('Matrix top keys', ['roles','permissions'], array_keys($r['data']));
if (!empty($r['data']['roles'])) {
    check('Matrix > roles[0] keys', ['role','permissions'], array_keys($r['data']['roles'][0]));
    check('Matrix > roles[0].role keys',
        ['id','name','slug','users_count'],
        array_keys($r['data']['roles'][0]['role'])
    );
    check('Matrix > roles[0].permissions[0] keys',
        ['id','slug','name','group','enabled'],
        array_keys($r['data']['roles'][0]['permissions'][0])
    );
}
if (!empty($r['data']['permissions'])) {
    check('Matrix > permissions[0] keys',
        ['id','name','slug','group'],
        array_keys($r['data']['permissions'][0])
    );
}

// 4) LIST PERMISSIONS
echo PHP_EOL . "=== GET /admin/permissions ===" . PHP_EOL;
$r = req('/admin/permissions');
check('Permission item keys',
    ['id','name','slug','group','description','created_at','updated_at'],
    array_keys($r['data'][0])
);

// 5) PERMISSIONS GROUPED
echo PHP_EOL . "=== GET /admin/permissions/grouped ===" . PHP_EOL;
$r = req('/admin/permissions/grouped');
check('PermissionGroup keys',
    ['group','permissions'],
    array_keys($r['data'][0])
);
check('PermissionGroup > permissions[0] keys',
    ['id','name','slug','description'],
    array_keys($r['data'][0]['permissions'][0])
);

// Summary
echo PHP_EOL . "══════════════════════════════════════════════" . PHP_EOL;
echo "  Total: " . ($pass + $fail) . " | ✅ Passed: {$pass} | ❌ Failed: {$fail}" . PHP_EOL;
echo "══════════════════════════════════════════════" . PHP_EOL;
