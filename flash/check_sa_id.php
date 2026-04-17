<?php
$j = file_get_contents('storage/app/google/service-account.json');
$d = json_decode($j, true);
echo 'project_id=' . ($d['project_id'] ?? 'NOT_FOUND') . PHP_EOL;
echo 'client_email=' . ($d['client_email'] ?? 'NOT_FOUND') . PHP_EOL;
