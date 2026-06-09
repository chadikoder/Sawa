<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$rows = db()->query('SELECT id, slug, name_en, name_ar FROM categories WHERE active = 1 ORDER BY sort_order')->fetchAll();
json_response(['categories' => $rows]);
