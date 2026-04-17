<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test 1: Text-to-image via imagen-3.0-generate-002 ===" . PHP_EOL;
$service = new App\Services\GeminiService('image', '3:4');
$result = $service->chatWithParts([], [['text' => 'Arabic guy on a camel running fast in a desert, jet fighter flying far in the sky, night time, camera flash effect']]);

echo 'success=' . var_export($result['success'] ?? null, true) . PHP_EOL;
echo 'images=' . (isset($result['images']) && is_array($result['images']) ? count($result['images']) : 'missing') . PHP_EOL;
echo 'error=' . (($result['error'] ?? null) === null ? 'NULL' : $result['error']) . PHP_EOL;

if (!empty($result['images'])) {
    $imgData = base64_decode($result['images'][0]['data']);
    $outPath = 'storage/app/public/test_imagen_output.png';
    file_put_contents($outPath, $imgData);
    echo 'saved=' . $outPath . ' (' . strlen($imgData) . ' bytes)' . PHP_EOL;
}
