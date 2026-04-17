<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

App\Models\Setting::setValue('gemini_auth_method', 'api_key');
App\Models\Setting::setValue('gemini_image_model', 'gemini-3.1-flash-image-preview');

echo 'gemini_auth_method=' . App\Models\Setting::getValue('gemini_auth_method') . PHP_EOL;
echo 'gemini_image_model=' . App\Models\Setting::getValue('gemini_image_model') . PHP_EOL;
