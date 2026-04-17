<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

App\Models\Setting::setValue('gemini_image_model', 'imagen-3.0-generate-002');

$service = new App\Services\GeminiService('image', '1:1');
$ref = new ReflectionClass($service);
$configuredProp = $ref->getProperty('configuredImageModel');
$configuredProp->setAccessible(true);
$imageProp = $ref->getProperty('imageModel');
$imageProp->setAccessible(true);

echo 'DB setting   = ' . App\Models\Setting::getValue('gemini_image_model') . PHP_EOL;
echo 'configured   = ' . $configuredProp->getValue($service) . PHP_EOL;
echo 'effective    = ' . $imageProp->getValue($service) . PHP_EOL;
echo 'isImagenModel= ' . (str_starts_with($imageProp->getValue($service), 'imagen') ? 'YES' : 'NO') . PHP_EOL;
echo 'active       = ' . $service->getModel() . PHP_EOL;
