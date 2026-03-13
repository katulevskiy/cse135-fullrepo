<?php
// /cgi-bin/hw2/php/state.php
session_name("CGISESSID");
session_save_path("/tmp/hw2_sessions");
@mkdir("/tmp/hw2_sessions", 0700, true);
session_start();

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

// read from GET, else fall back to session
if (isset($_GET["username"]) && $_GET["username"] !== "") {
    $_SESSION["username"] = $_GET["username"];
}

$name = $_SESSION["username"] ?? null;
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PHP Sessions</title></head>
<body>
  <h1>PHP Sessions Page</h1>

  <?php if ($name): ?>
    <p><b>Name:</b> <?= htmlspecialchars($name) ?></p>
  <?php else: ?>
    <p><b>Name:</b> You do not have a name set</p>
  <?php endif; ?>

  <br/><br/>
  <a href="/cgi-bin/hw2/php/state-set.php">PHP CGI Form</a><br/>

  <form style="margin-top:30px" action="/cgi-bin/hw2/php/state-clear.php" method="get">
    <button type="submit">Destroy Session</button>
  </form>
</body>
</html>
