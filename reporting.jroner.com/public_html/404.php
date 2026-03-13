<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found — Analytics</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f1623; color: #c8d6e8; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 40px; max-width: 480px; }
        .code { font-size: 80px; font-weight: 700; color: #2a3448; line-height: 1; margin-bottom: 16px; }
        h1 { font-size: 24px; font-weight: 600; color: #f0f4ff; margin-bottom: 10px; }
        p { font-size: 14px; color: #7a8fa6; margin-bottom: 28px; line-height: 1.6; }
        a { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; background: #4f8ef7; color: #fff; border-radius: 6px; text-decoration: none; font-size: 13.5px; font-weight: 500; transition: background 0.12s; }
        a:hover { background: #3b7de8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">404</div>
        <h1>Page Not Found</h1>
        <p>The page you were looking for doesn't exist. It may have been moved, deleted, or you may have mistyped the URL.</p>
        <a href="/index.php">← Back to Dashboard</a>
    </div>
</body>
</html>
