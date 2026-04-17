<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set the model
App\Models\Setting::setValue('gemini_image_model', 'gemini-3.1-flash-image-preview');

// Verify
$s = new App\Services\GeminiService('image', '1:1');
$r = new ReflectionClass($s);
$p = $r->getProperty('imageModel');
$p->setAccessible(true);
echo 'effective_model=' . $p->getValue($s) . PHP_EOL;

$projectId = $r->getProperty('projectId');
$projectId->setAccessible(true);
echo 'project_id=' . $projectId->getValue($s) . PHP_EOL;

$region = $r->getProperty('region');
$region->setAccessible(true);
echo 'region=' . $region->getValue($s) . PHP_EOL;

// Test direct API call with text-to-image (no inline image)
echo PHP_EOL . '--- Testing text-to-image with gemini-3.1-flash-image-preview ---' . PHP_EOL;
$result = $s->chatWithParts([], [['text' => 'A beautiful red rose']]);
echo 'success=' . var_export($result['success'] ?? null, true) . PHP_EOL;
echo 'images=' . (isset($result['images']) && is_array($result['images']) ? count($result['images']) : 'missing') . PHP_EOL;
echo 'error=' . (($result['error'] ?? null) === null ? 'NULL' : $result['error']) . PHP_EOL;
