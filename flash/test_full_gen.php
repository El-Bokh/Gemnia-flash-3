<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Full generation test with aspect ratio fix ===" . PHP_EOL;
$service = new App\Services\GeminiService('image', '1:1'); // frontend default
$result = $service->chatWithParts([], [['text' => 'Arabic guy on a camel running fast in a desert, medium distance of the camera, jet fighter fllying far in the sky Night time Completely dark sky Camera flash effect Real iphone camera shot Aspect ratio 3:4']]);

echo 'success=' . var_export($result['success'] ?? null, true) . PHP_EOL;
echo 'images=' . (isset($result['images']) && is_array($result['images']) ? count($result['images']) : 'missing') . PHP_EOL;
echo 'error=' . (($result['error'] ?? null) === null ? 'NULL' : $result['error']) . PHP_EOL;
