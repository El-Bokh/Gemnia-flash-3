<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Setting;

function outputValue(string $label, mixed $value): void
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    echo $label . '=' . $value . PHP_EOL;
}

function maskSecret(?string $value): string
{
    if ($value === null || $value === '') {
        return 'empty';
    }

    if (strlen($value) <= 12) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 6) . '...' . substr($value, -6);
}

$credentialsPath = config('services.vertex_ai.credentials_path');
$serviceAccount = null;

if (is_string($credentialsPath) && $credentialsPath !== '' && file_exists($credentialsPath)) {
    $json = json_decode(file_get_contents($credentialsPath), true);
    if (is_array($json)) {
        $serviceAccount = $json;
    }
}

outputValue('app_env', env('APP_ENV'));
outputValue('app_url', env('APP_URL'));
outputValue('db_database', env('DB_DATABASE'));
outputValue('vertex_project_id', config('services.vertex_ai.project_id'));
outputValue('vertex_region', config('services.vertex_ai.region'));
outputValue('credentials_path', $credentialsPath);
outputValue('credentials_exists', is_string($credentialsPath) && file_exists($credentialsPath));

outputValue('setting.gemini_auth_method', Setting::getValue('gemini_auth_method', 'missing'));
outputValue('setting.gemini_image_model', Setting::getValue('gemini_image_model', 'missing'));
outputValue('setting.gemini_text_model', Setting::getValue('gemini_text_model', 'missing'));
outputValue('setting.gemini_api_key', maskSecret(Setting::getValue('gemini_api_key', '')));

if ($serviceAccount !== null) {
    outputValue('service_account.project_id', $serviceAccount['project_id'] ?? 'missing');
    outputValue('service_account.client_email', $serviceAccount['client_email'] ?? 'missing');
    outputValue('service_account.private_key_id', $serviceAccount['private_key_id'] ?? 'missing');
} else {
    outputValue('service_account', 'unavailable');
}
