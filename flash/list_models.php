<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Google\Auth\Credentials\ServiceAccountCredentials;

$saPath = storage_path('app/google/service-account.json');
$credentials = new ServiceAccountCredentials(
    ['https://www.googleapis.com/auth/cloud-platform'],
    json_decode(file_get_contents($saPath), true)
);
$token = $credentials->fetchAuthToken()['access_token'];

$projectId = config('services.vertex_ai.project_id');
$region = config('services.vertex_ai.region');

// List publisher models - try to find gemini image models
$url = "https://{$region}-aiplatform.googleapis.com/v1beta1/projects/{$projectId}/locations/{$region}/publishers/google/models";
echo "URL: {$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json",
    ],
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: {$status}\n";
if ($status !== 200) {
    echo "Response: " . substr($response, 0, 500) . "\n";
} else {
    $data = json_decode($response, true);
    $models = $data['publisherModels'] ?? $data['models'] ?? [];
    echo "Found " . count($models) . " models\n";
    foreach ($models as $m) {
        $name = $m['name'] ?? $m['model'] ?? 'unknown';
        if (stripos($name, 'gemini') !== false || stripos($name, 'image') !== false || stripos($name, 'imagen') !== false) {
            echo "  - {$name}\n";
        }
    }
}

// Also test specific models to see which ones exist
echo "\n--- Testing specific models ---\n";
$modelsToTest = [
    'gemini-3.1-flash-image-preview',
    'gemini-2.5-flash-preview-image-generation',
    'gemini-2.0-flash-exp',
    'gemini-2.0-flash-001',
    'gemini-2.5-flash-image',
    'gemini-2.5-flash-preview-04-17',
    'gemini-2.5-flash',
];

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
    $emoji = $code === 200 ? 'OK' : 'FAIL';
    echo "  [{$emoji}] {$model} -> HTTP {$code}\n";
}
