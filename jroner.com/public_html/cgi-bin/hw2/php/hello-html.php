<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$time = date("Y-m-d H:i:s");
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Hello CGI World - Jacob Roner</title></head>
<body>
  <h1 align="center">Hello HTML World - Jacob Roner</h1><hr/>
  <p>Hello World, from Jacob Roner</p>
  <p>This page was generated with the PHP programming language from jroner.com</p>
  <p>This program was generated at: <?= htmlspecialchars($time) ?></p>
  <p>Your current IP Address is: <?= htmlspecialchars($ip) ?></p>
</body>
</html>
