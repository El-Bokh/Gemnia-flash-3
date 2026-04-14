<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$path = config('services.vertex_ai.credentials_path');
echo "Path: {$path}\n";
echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";

if (file_exists($path)) {
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    echo "Valid JSON: " . ($data ? 'YES' : 'NO') . "\n";
    echo "Has client_email: " . (!empty($data['client_email']) ? 'YES' : 'NO') . "\n";
    echo "Has private_key: " . (!empty($data['private_key']) ? 'YES' : 'NO') . "\n";
    echo "Project ID: " . ($data['project_id'] ?? 'MISSING') . "\n";
} else {
    // Try alternative paths
    $alt1 = storage_path('app/google/service-account.json');
    echo "Alt path: {$alt1}\n";
    echo "Alt exists: " . (file_exists($alt1) ? 'YES' : 'NO') . "\n";
    
    // List what's in storage/app/google/
    $dir = storage_path('app/google');
    echo "Dir exists: " . (is_dir($dir) ? 'YES' : 'NO') . "\n";
    if (is_dir($dir)) {
        echo "Files: " . implode(', ', scandir($dir)) . "\n";
    }
}

// Check auth method setting
$authMethod = \App\Models\Setting::getValue('gemini_auth_method', 'service_account');
echo "Auth method: {$authMethod}\n";
