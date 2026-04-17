<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$req = App\Models\AiRequest::query()->latest('id')->first();
if (! $req) {
    echo "NO_REQUESTS\n";
    exit;
}

$data = [
    'id' => $req->id,
    'status' => $req->status,
    'type' => $req->type,
    'model_used' => $req->model_used,
    'error_message' => $req->error_message,
    'created_at' => (string) $req->created_at,
    'user_prompt' => $req->user_prompt,
    'processed_prompt' => $req->processed_prompt,
];

foreach ($data as $key => $value) {
    echo $key . '=' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value)) . PHP_EOL;
}
