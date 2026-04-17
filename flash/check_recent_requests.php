<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$reqs = App\Models\AiRequest::query()->latest('id')->take(10)->get();
foreach ($reqs as $r) {
    echo "---\n";
    echo "id={$r->id} type={$r->type} status={$r->status} model={$r->model_used}\n";
    echo "prompt=" . mb_substr($r->user_prompt ?? '', 0, 100) . "\n";
    echo "error=" . ($r->error_message ?? 'NULL') . "\n";
    echo "time={$r->processing_time_ms}ms at={$r->created_at}\n";
}
