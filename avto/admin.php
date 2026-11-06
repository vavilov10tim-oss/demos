<?php
// Мини-админка трекинга заказов (RSI GARANT — привоз авто).
// Статусы хранятся в leads/track.json. Доступ по паролю (сменить ADMIN_PASS при деплое).
session_start();

const ADMIN_PASS = 'rsi-auto-2026'; // СМЕНИТЬ при деплое
const STAGES = ['Заявка и подбор', 'Выкуп в Европе', 'Автовоз до границы', 'Таможня и ЭПТС', 'Выдача ключей'];

$file = __DIR__ . '/leads/track.json';
if (!is_dir(__DIR__ . '/leads')) @mkdir(__DIR__ . '/leads', 0700, true);
$orders = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

function save(array $orders, string $file): void {
    file_put_contents($file, json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// вход / выход
if (isset($_GET['logout'])) { unset($_SESSION['adm']); header('Location: admin.php'); exit; }
if (isset($_POST['pass'])) {
    if (hash_equals(ADMIN_PASS, (string)$_POST['pass'])) $_SESSION['adm'] = true;
    else $err = 'Неверный пароль';
}
$authed = !empty($_SESSION['adm']);

// действия
$msg = '';
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['pass'])) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $code = strtoupper(trim((string)$_POST['code']));
        $car = trim((string)$_POST['car']);
        if (preg_match('/^[A-Z0-9-]{3,20}$/', $code) && $car !== '' && empty($orders[$code])) {
            $orders[$code] = ['car' => $car, 'stage' => 0, 'comment' => 'Заказ создан, идёт подбор.', 'updated' => date('d.m.Y')];
            save($orders, $file);
            $msg = "Заказ $code добавлен";
        } else $msg = 'Проверьте код (A-Z, 0-9, дефис) и уникальность';
    }
    if ($act === 'upd') {
        $code = (string)$_POST['code'];
        if (isset($orders[$code])) {
            $orders[$code]['stage'] = max(0, min(4, (int)$_POST['stage']));
            $orders[$code]['comment'] = trim((string)$_POST['comment']);
            $orders[$code]['car'] = trim((string)$_POST['car']) ?: $orders[$code]['car'];
            $orders[$code]['updated'] = date('d.m.Y');
            save($orders, $file);
            $msg = "Заказ $code обновлён";
        }
    }
    if ($act === 'del') {
        $code = (string)$_POST['code'];
        unset($orders[$code]);
        save($orders, $file);
        $msg = "Заказ $code удалён";
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Трекинг — админка RSI</title>
<style>
body{font-family:-apple-system,'Segoe UI',sans-serif;background:#f4f1ed;color:#171617;margin:0;padding:40px 20px}
.box{max-width:860px;margin:0 auto}
h1{font-size:22px;margin-bottom:20px}
.card{background:#fff;border:1px solid #e1ddd7;border-radius:14px;padding:20px;margin-bottom:14px}
input,select,textarea{width:100%;padding:10px 12px;border:1.5px solid #e1ddd7;border-radius:9px;font:inherit;margin:4px 0;box-sizing:border-box}
button{padding:10px 18px;border:0;border-radius:999px;background:#5C1113;color:#fff;font:inherit;font-weight:600;cursor:pointer}
button.ghost{background:#eee;color:#171617}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.msg{background:#e9dcc0;border-radius:9px;padding:10px 14px;margin-bottom:14px}
.top{display:flex;justify-content:space-between;align-items:center}
code{background:#f4f1ed;padding:2px 8px;border-radius:6px}
</style>
</head>
<body><div class="box">
<?php if (!$authed): ?>
  <h1>Вход в админку трекинга</h1>
  <?php if (!empty($err)): ?><div class="msg"><?= h($err) ?></div><?php endif; ?>
  <form class="card" method="post">
    <input type="password" name="pass" placeholder="Пароль" autofocus>
    <button>Войти</button>
  </form>
<?php else: ?>
  <div class="top"><h1>Заказы (<?= count($orders) ?>)</h1><a href="?logout=1"><button class="ghost">Выйти</button></a></div>
  <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>

  <form class="card" method="post">
    <b>Новый заказ</b>
    <div class="row" style="margin-top:8px">
      <input type="hidden" name="act" value="add">
      <input name="code" placeholder="RSI-0001" style="max-width:150px" required>
      <input name="car" placeholder="BMW X3, 2022 · из Германии" style="flex:1" required>
      <button>Добавить</button>
    </div>
  </form>

  <?php foreach ($orders as $code => $o): ?>
  <form class="card" method="post">
    <input type="hidden" name="act" value="upd">
    <input type="hidden" name="code" value="<?= h($code) ?>">
    <div class="row" style="justify-content:space-between">
      <b><?= h($code) ?></b>
      <span style="color:#6B6B6B;font-size:13px">обновлён <?= h($o['updated'] ?? '') ?></span>
    </div>
    <input name="car" value="<?= h($o['car'] ?? '') ?>">
    <div class="row">
      <select name="stage" style="max-width:260px">
        <?php foreach (STAGES as $i => $s): ?>
        <option value="<?= $i ?>" <?= ($o['stage'] ?? 0) == $i ? 'selected' : '' ?>><?= $i + 1 ?>. <?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <textarea name="comment" rows="2"><?= h($o['comment'] ?? '') ?></textarea>
    <div class="row">
      <button>Сохранить</button>
      <button class="ghost" formaction="admin.php" name="act" value="del" onclick="this.form.act.value='del';return confirm('Удалить заказ <?= h($code) ?>?')">Удалить</button>
    </div>
  </form>
  <?php endforeach; ?>
<?php endif; ?>
</div></body></html>
