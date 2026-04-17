<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$keys = [
    'gemini_auth_method',
    'gemini_api_key',
    'gemini_image_model',
    'gemini_text_model',
];

foreach ($keys as $key) {
    $value = App\Models\Setting::getValue($key, 'MISSING');
    if ($key === 'gemini_api_key' && is_string($value) && $value !== 'MISSING') {
        $masked = strlen($value) > 12
            ? substr($value, 0, 6) . '...' . substr($value, -6)
            : $value;
        echo $key . '=' . $masked . ' len=' . strlen($value) . PHP_EOL;
        continue;
    }

    echo $key . '=' . (is_scalar($value) ? $value : json_encode($value)) . PHP_EOL;
}
