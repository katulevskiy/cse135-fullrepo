<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$headers = function_exists("getallheaders") ? getallheaders() : [];
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PHP Environment</title></head>
<body>
  <h1>PHP Environment</h1>

  <h2>Request</h2>
  <ul>
    <li><b>Method:</b> <?= h($_SERVER["REQUEST_METHOD"] ?? "") ?></li>
    <li><b>URI:</b> <?= h($_SERVER["REQUEST_URI"] ?? "") ?></li>
    <li><b>Query String:</b> <?= h($_SERVER["QUERY_STRING"] ?? "") ?></li>
    <li><b>Remote Addr:</b> <?= h($_SERVER["REMOTE_ADDR"] ?? "") ?></li>
  </ul>

  <h2>Headers</h2>
  <pre><?php
foreach ($headers as $k => $v) {
  echo h($k) . ": " . h($v) . "\n";
}
?></pre>

  <h2>$_SERVER</h2>
  <pre><?php
ksort($_SERVER);
foreach ($_SERVER as $k => $v) {
  echo h($k) . "=" . h($v) . "\n";
}
?></pre>
</body>
</html>
