<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$s = new App\Services\GeminiService('image','1:1');
$r = new ReflectionClass($s);
$p = $r->getProperty('imageModel');
$p->setAccessible(true);
echo 'effective=' . $p->getValue($s) . PHP_EOL;
echo 'setting=' . App\Models\Setting::getValue('gemini_image_model','missing') . PHP_EOL;
