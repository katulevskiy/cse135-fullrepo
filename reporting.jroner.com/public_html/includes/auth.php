<?php
require_once __DIR__ . '/db.php';

$ROLE_WEIGHT = ['viewer' => 1, 'analyst' => 2, 'super_admin' => 3];

function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return $_SESSION['user_data'] ?? null;
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $minRole): void {
    global $ROLE_WEIGHT;
    requireLogin();
    $user = getCurrentUser();
    $userWeight = $ROLE_WEIGHT[$user['role']] ?? 0;
    $minWeight  = $ROLE_WEIGHT[$minRole] ?? 999;
    if ($userWeight < $minWeight) {
        header('Location: /403.php');
        exit;
    }
}

function requireSection(string $section): void {
    requireLogin();
    $user = getCurrentUser();
    if ($user['role'] === 'super_admin') return;
    if ($user['role'] === 'analyst' && in_array($section, $user['sections'] ?? [])) return;
    header('Location: /403.php');
    exit;
}

function canSeeSection(string $section): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    if ($user['role'] === 'super_admin') return true;
    if ($user['role'] === 'analyst' && in_array($section, $user['sections'] ?? [])) return true;
    return false;
}

function loadUserFromDb(int $userId): ?array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) return null;

    $user['sections'] = [];
    if ($user['role'] === 'analyst') {
        $s = $db->prepare("SELECT section FROM analyst_sections WHERE user_id = ?");
        $s->bind_param("i", $userId);
        $s->execute();
        $sr = $s->get_result();
        while ($row = $sr->fetch_assoc()) {
            $user['sections'][] = $row['section'];
        }
    }
    return $user;
}

function renderNav(string $activePage = ''): void {
    $user = getCurrentUser();
    if (!$user) return;
    $role = $user['role'];

    $base = '/reports/';
    $adminBase = '/admin/';
    $navItems = [];

    if (canSeeSection('visitor')) {
        $navItems[] = ['href' => $base . 'visitor.php',     'label' => 'Visitor',     'key' => 'visitor'];
    }
    if (canSeeSection('performance')) {
        $navItems[] = ['href' => $base . 'performance.php', 'label' => 'Performance', 'key' => 'performance'];
    }
    if (canSeeSection('behavioral')) {
        $navItems[] = ['href' => $base . 'behavioral.php',  'label' => 'Behavioral',  'key' => 'behavioral'];
    }
    $navItems[] = ['href' => $base . 'saved.php', 'label' => 'Saved Reports', 'key' => 'saved'];

    $isAdmin = ($role === 'super_admin');

    echo '<nav>';
    echo '<a class="nav-brand" href="/index.php">';
    echo '<div class="nav-brand-icon"><svg viewBox="0 0 24 24"><path d="M3 13h2v8H3v-8zm4-5h2v13H7V8zm4-4h2v17h-2V4zm4 7h2v10h-2V11zm4-3h2v13h-2V8z"/></svg></div>';
    echo '<span class="nav-brand-text">Analytics</span>';
    echo '</a>';

    foreach ($navItems as $item) {
        $cls = ($activePage === $item['key']) ? ' class="active"' : '';
        echo '<a href="' . $item['href'] . '"' . $cls . '>' . $item['label'] . '</a>';
    }

    if ($isAdmin) {
        $cls = ($activePage === 'admin') ? ' class="active"' : '';
        echo '<a href="' . $adminBase . 'index.php"' . $cls . '>Admin</a>';
    }

    echo '<div class="nav-right">';
    echo '<span class="nav-user">' . htmlspecialchars($user['username']) . ' <span class="nav-role">' . htmlspecialchars($role) . '</span></span>';
    echo '<a href="/logout.php" class="logout">Logout</a>';
    echo '</div>';
    echo '</nav>';
}

// Shared nav + page CSS
function pageHead(string $title, string $extraCss = ''): void {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' — Analytics</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f1623; color: #c8d6e8; min-height: 100vh; }
        nav { background: #1a2133; border-bottom: 1px solid #2a3448; display: flex; align-items: center; padding: 0 28px; height: 54px; gap: 4px; flex-wrap: wrap; }
        .nav-brand { display: flex; align-items: center; gap: 8px; margin-right: 20px; text-decoration: none; }
        .nav-brand-icon { width: 28px; height: 28px; background: #4f8ef7; border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .nav-brand-icon svg { width: 15px; height: 15px; fill: #fff; }
        .nav-brand-text { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        nav a { text-decoration: none; font-size: 13.5px; color: #7a8fa6; padding: 6px 12px; border-radius: 6px; transition: background 0.12s, color 0.12s; }
        nav a:hover { background: #232d42; color: #c8d6e8; }
        nav a.active { background: #1e2d4a; color: #4f8ef7; font-weight: 500; }
        .nav-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .nav-user { font-size: 13px; color: #5a7090; }
        .nav-role { background: #2a3448; border-radius: 4px; padding: 1px 6px; font-size: 11px; color: #7a8fa6; }
        nav a.logout { color: #f87171; }
        nav a.logout:hover { background: #2d1a1a; color: #f87171; }
        .content { padding: 32px 36px; max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 20px; font-weight: 600; color: #f0f4ff; margin-bottom: 4px; }
        .page-sub { font-size: 13px; color: #5a7090; margin-bottom: 28px; }
        .card { background: #1a2133; border: 1px solid #2a3448; border-radius: 10px; padding: 24px; }
        .card-title { font-size: 14px; font-weight: 600; color: #e2e8f0; margin-bottom: 4px; }
        .card-sub { font-size: 12px; color: #5a7090; margin-bottom: 16px; }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .charts-grid.three { grid-template-columns: repeat(3, 1fr); }
        @media (max-width: 900px) { .charts-grid, .charts-grid.three { grid-template-columns: 1fr; } }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px 12px; background: #131d2e; color: #7a8fa6; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #2a3448; }
        td { padding: 9px 12px; border-bottom: 1px solid #1e2a3a; color: #c8d6e8; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #1e2a3a; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: background 0.12s; }
        .btn-primary { background: #4f8ef7; color: #fff; }
        .btn-primary:hover { background: #3b7de8; }
        .btn-secondary { background: #2a3448; color: #c8d6e8; }
        .btn-secondary:hover { background: #344057; }
        .btn-danger { background: #7c2d2d; color: #f87171; }
        .btn-danger:hover { background: #9b3333; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        .badge-visitor { background: #1a3a5c; color: #60a5fa; }
        .badge-performance { background: #1a3a2c; color: #34d399; }
        .badge-behavioral { background: #3a2a1a; color: #fbbf24; }
        .badge-super_admin { background: #3a1a3a; color: #e879f9; }
        .badge-analyst { background: #1a2a3a; color: #60a5fa; }
        .badge-viewer { background: #2a2a1a; color: #fbbf24; }
        textarea { width: 100%; background: #0f1623; border: 1px solid #2a3448; border-radius: 6px; padding: 10px 13px; font-size: 13px; color: #e2e8f0; outline: none; resize: vertical; min-height: 100px; font-family: system-ui, -apple-system, sans-serif; }
        textarea:focus { border-color: #4f8ef7; }
        .alert { padding: 10px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #1a3a2c; border: 1px solid #2d6a4a; color: #34d399; }
        .alert-error { background: #2d1a1a; border: 1px solid #7c2d2d; color: #f87171; }
        ' . $extraCss . '
    </style>
</head>
<body>
';
}
