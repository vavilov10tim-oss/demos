<?php
// Приём заявок с сайта «Привоз авто из Европы» (RSI GARANT): письмо на news@rsi.capital + копия в leads/leads.csv.
// Кладётся в корень сайта рядом с index.html. Нужен обычный PHP-хостинг с функцией mail().

const TO = 'news@rsi.capital';
const FORMS = [
    'quiz'    => 'Заявка: привоз авто (квиз)',
    'contact' => 'Заявка: привоз авто (контакты)',
];
const MAX_FIELD = 2000;
const MIN_INTERVAL = 20; // секунд между заявками с одного IP

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $code, string $status): void {
    http_response_code($code);
    echo json_encode(['status' => $status]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, 'method');

$form = $_POST['form-name'] ?? '';
if (!isset(FORMS[$form])) reply(400, 'form');
// ловушка для ботов: скрытое поле люди не заполняют
if (!empty($_POST['bot-field'])) reply(200, 'ok');

$fields = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, ['form-name', 'bot-field'], true) || is_array($value)) continue;
    $value = trim(mb_substr((string)$value, 0, MAX_FIELD));
    if ($value !== '') $fields[mb_substr((string)$key, 0, 60)] = $value;
}
if (empty($fields['Имя']) || empty($fields['Телефон'])) reply(422, 'fields');

$dir = __DIR__ . '/leads';
if (!is_dir($dir)) @mkdir($dir, 0700, true);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$stamp = $dir . '/.ip-' . md5($ip);
if (is_file($stamp) && time() - filemtime($stamp) < MIN_INTERVAL) reply(429, 'slow');
@touch($stamp);

$when = date('d.m.Y H:i');
$lines = [FORMS[$form], 'Время: ' . $when, ''];
foreach ($fields as $k => $v) $lines[] = $k . ': ' . $v;
$lines[] = '';
$lines[] = 'Страница: ' . ($_SERVER['HTTP_REFERER'] ?? '—');
$body = implode("\n", $lines);

$host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/^www\./', '', $host);
$subject = '=?UTF-8?B?' . base64_encode(FORMS[$form] . ' — ' . $fields['Имя']) . '?=';
$headers = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode('Сайт RSI GARANT — авто') . '?= <noreply@' . $host . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);
$sent = mail(TO, $subject, $body, $headers);

// копия заявки на сервере, чтобы ничего не потерялось, даже если письмо не дошло
$csv = fopen($dir . '/leads.csv', 'a');
if ($csv) {
    fputcsv($csv, [$when, $form, json_encode($fields, JSON_UNESCAPED_UNICODE), $sent ? 'отправлено' : 'ошибка почты']);
    fclose($csv);
}

$sent ? reply(200, 'ok') : reply(500, 'mail');
