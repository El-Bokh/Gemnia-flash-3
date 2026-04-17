<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = App\Models\AiRequest::query()->latest('id')->first();
echo 'id=' . $r->id . PHP_EOL;
echo 'status=' . $r->status . PHP_EOL;
echo 'model=' . $r->model_used . PHP_EOL;
echo 'error=' . ($r->error_message ?? 'NULL') . PHP_EOL;
echo 'at=' . $r->created_at . PHP_EOL;
