<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test different Imagen models via the predict endpoint
$service = new App\Services\GeminiService('image', '1:1');
$ref = new ReflectionClass($service);
$runImagen = $ref->getMethod('runImagenPredict');
$runImagen->setAccessible(true);

$modelsToTest = [
    'imagen-3.0-generate-002',
    'imagen-3.0-generate-001',
    'imagen-3.0-fast-generate-001',
    'imagen-4.0-generate-001',
    'imagen-4.0-fast-generate-001',
    'imagen-4.0-ultra-generate-001',
];

$prompt = 'A beautiful red rose in a garden, photorealistic, ultra detailed';

foreach ($modelsToTest as $model) {
    echo "Testing {$model}... ";
    $result = $runImagen->invoke($service, $prompt, '1:1', null, $model);
    if ($result['success']) {
        echo "SUCCESS (images=" . count($result['images']) . ")\n";
    } else {
        echo "FAIL: " . ($result['error'] ?? 'unknown') . "\n";
    }
}
