<?php
// Трекинг заказов: отдаёт статус из leads/track.json по номеру заказа.
// GET track.php?order=RSI-DEMO → {"ok":true,"order":{...}}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$code = strtoupper(trim((string)($_GET['order'] ?? '')));
if (!preg_match('/^[A-ZА-Я0-9-]{3,20}$/u', $code)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$file = __DIR__ . '/leads/track.json';
$orders = is_file($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($orders) || empty($orders[$code]) || !is_array($orders[$code])) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$o = $orders[$code];
echo json_encode([
    'ok' => true,
    'order' => [
        'car' => (string)($o['car'] ?? ''),
        'stage' => max(0, min(4, (int)($o['stage'] ?? 0))),
        'comment' => (string)($o['comment'] ?? ''),
        'updated' => (string)($o['updated'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE);
