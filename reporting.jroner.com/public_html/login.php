<?php
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $db = getDb();
        $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $fullUser = loadUserFromDb($user['id']);
            $_SESSION['user_data'] = $fullUser;
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Analytics Dashboard</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f1623; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1a2133; border: 1px solid #2a3448; border-radius: 10px; padding: 44px 48px; width: 100%; max-width: 400px; }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; }
        .logo-icon { width: 34px; height: 34px; background: #4f8ef7; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .logo-icon svg { width: 18px; height: 18px; fill: #fff; }
        .logo-text { font-size: 15px; font-weight: 600; color: #e2e8f0; letter-spacing: 0.01em; }
        h1 { font-size: 22px; font-weight: 600; color: #f0f4ff; margin-bottom: 6px; }
        .subtitle { font-size: 13.5px; color: #7a8fa6; margin-bottom: 28px; }
        .error { background: #2d1a1a; border: 1px solid #7c2d2d; color: #f87171; font-size: 13px; padding: 10px 14px; border-radius: 6px; margin-bottom: 20px; }
        label { display: block; font-size: 12.5px; font-weight: 500; color: #94a3b8; margin-bottom: 6px; letter-spacing: 0.04em; text-transform: uppercase; }
        input[type="text"], input[type="password"] { width: 100%; background: #0f1623; border: 1px solid #2a3448; border-radius: 6px; padding: 10px 13px; font-size: 14px; color: #e2e8f0; outline: none; transition: border-color 0.15s; margin-bottom: 18px; }
        input[type="text"]:focus, input[type="password"]:focus { border-color: #4f8ef7; }
        input::placeholder { color: #3d4f66; }
        button { width: 100%; background: #4f8ef7; color: #fff; border: none; border-radius: 6px; padding: 11px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 4px; transition: background 0.15s; }
        button:hover { background: #3b7de8; }
        button:active { background: #2f6fd4; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M3 13h2v8H3v-8zm4-5h2v13H7V8zm4-4h2v17h-2V4zm4 7h2v10h-2V11zm4-3h2v13h-2V8z"/></svg>
            </div>
            <span class="logo-text">CSE 135 Analytics</span>
        </div>
        <h1>Sign in</h1>
        <p class="subtitle">Enter your credentials to access the dashboard.</p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="on">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   autocomplete="username" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••"
                   autocomplete="current-password" required>
            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
