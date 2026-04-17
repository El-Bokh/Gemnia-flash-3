<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Keep API key stored but switch back to service_account (which works)
App\Models\Setting::setValue('gemini_auth_method', 'service_account');
App\Models\Setting::setValue('gemini_image_model', 'gemini-2.5-flash-image');

echo 'auth_method=' . App\Models\Setting::getValue('gemini_auth_method') . PHP_EOL;
echo 'image_model=' . App\Models\Setting::getValue('gemini_image_model') . PHP_EOL;

// Test with aspect ratio from prompt
echo PHP_EOL . '--- Test aspect ratio detection ---' . PHP_EOL;
$service = new App\Services\GeminiService('image', '1:1'); // frontend sends 1:1 default
$ref = new ReflectionClass($service);
$detect = $ref->getMethod('detectAspectRatio');
$detect->setAccessible(true);

echo 'prompt "Aspect ratio 3:4" => ' . $detect->invoke($service, 'Arabic guy on camel Aspect ratio 3:4') . PHP_EOL;
echo 'prompt "نسبة 16:9" => ' . $detect->invoke($service, 'مشهد سينمائي نسبة 16:9') . PHP_EOL;
echo 'prompt no ratio => ' . $detect->invoke($service, 'A beautiful rose') . PHP_EOL;
echo 'prompt "portrait" => ' . $detect->invoke($service, 'portrait photo of a man') . PHP_EOL;
