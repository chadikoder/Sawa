<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$items = CampaignService::listActive();
json_response(['campaigns' => $items, 'total' => count($items)]);
