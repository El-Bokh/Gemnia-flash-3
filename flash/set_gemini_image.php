<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

App\Models\Setting::setValue('gemini_image_model', 'gemini-2.5-flash-image');
echo 'Model set to: ' . App\Models\Setting::getValue('gemini_image_model') . PHP_EOL;
