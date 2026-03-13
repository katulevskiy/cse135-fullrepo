<?php
// /cgi-bin/hw2/php/state-set.php
session_name("CGISESSID");
session_save_path("/tmp/hw2_sessions");
@mkdir("/tmp/hw2_sessions", 0700, true);
session_start();

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

$sessId = session_id();
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PHP CGI Form</title></head>
<body>
  <h1>PHP CGI Form</h1>

  <p><b>Current Session ID:</b> <?= htmlspecialchars($sessId ?: "(none)") ?></p>

  <p>Enter a username and submit it to the PHP session page.</p>

  <form action="/cgi-bin/hw2/php/state.php" method="get">
    <label for="username">Username:</label>
    <input id="username" name="username" type="text" />
    <button type="submit">Save to Session</button>
  </form>

  <hr/>

  <ul>
    <li><a href="/cgi-bin/hw2/php/state.php">PHP Session</a></li>
    <li><a href="/cgi-bin/hw2/php/state-clear.php">Destroy Session</a></li>
  </ul>
</body>
</html>
