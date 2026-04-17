<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get token the same way GeminiService does
$s = new App\Services\GeminiService('image', '1:1');
$r = new ReflectionClass($s);
$m = $r->getMethod('getAccessToken');
$m->setAccessible(true);
$token = $m->invoke($s);

$projectId = config('services.vertex_ai.project_id');
$region = config('services.vertex_ai.region');

echo "Project: {$projectId}\n";
echo "Region: {$region}\n\n";

// Test specific models
$modelsToTest = [
    'gemini-3.1-flash-image-preview',
    'gemini-2.5-flash-preview-image-generation',  
    'gemini-2.0-flash-exp',
    'gemini-2.5-flash-image',
    'gemini-2.5-flash',
    'gemini-2.5-pro',
    'imagen-4.0-ultra-generate-001',
    'imagen-3.0-generate-002',
];

echo "--- Testing model availability ---\n";
foreach ($modelsToTest as $model) {
    $testUrl = "https://{$region}-aiplatform.googleapis.com/v1beta1/projects/{$projectId}/locations/{$region}/publishers/google/models/{$model}";
    
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status = $code === 200 ? 'AVAILABLE' : "FAIL({$code})";
    echo "  [{$status}] {$model}\n";
}
