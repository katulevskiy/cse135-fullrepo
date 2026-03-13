<?php
// /cgi-bin/hw2/php/state-clear.php
session_name("CGISESSID");
session_save_path("/tmp/hw2_sessions");
@mkdir("/tmp/hw2_sessions", 0700, true);
session_start();

// delete session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie("CGISESSID", "", time() - 3600, "/");
}
session_destroy();

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Session Destroyed</title></head>
<body>
  <h1>Session Destroyed</h1>
  <a href="/cgi-bin/hw2/php/state-set.php">Start New Session</a>
</body>
</html>
