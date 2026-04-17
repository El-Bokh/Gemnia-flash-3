<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the latest conversation messages
$msgs = App\Models\ConversationMessage::query()->latest('id')->take(15)->get(['id','conversation_id','ai_request_id','role','content','image_url','status','created_at']);
foreach ($msgs as $m) {
    echo "---\n";
    echo "id={$m->id} conv={$m->conversation_id} role={$m->role} status={$m->status} ai_req={$m->ai_request_id}\n";
    echo "content=" . mb_substr($m->content ?? '', 0, 80) . "\n";
    echo "image=" . ($m->image_url ?? 'NULL') . "\n";
    echo "at={$m->created_at}\n";
}

echo "\n\n=== Latest AiRequests ===\n";
$reqs = App\Models\AiRequest::query()->latest('id')->take(10)->get(['id','type','status','user_prompt','model_used','error_message','processing_time_ms','created_at']);
foreach ($reqs as $r) {
    echo "---\n";
    echo "id={$r->id} type={$r->type} status={$r->status} model={$r->model_used}\n";
    echo "prompt=" . mb_substr($r->user_prompt ?? '', 0, 100) . "\n";
    echo "error=" . ($r->error_message ?? 'NULL') . "\n";
    echo "time={$r->processing_time_ms}ms at={$r->created_at}\n";
}

echo "\n\n=== Log file tail ===\n";
$logFile = storage_path('logs/laravel.log');
$lines = file($logFile);
$tail = array_slice($lines, -50);
echo implode('', $tail);
