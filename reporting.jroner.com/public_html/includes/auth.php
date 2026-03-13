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
    echo '<select id="tz-select" title="Display timezone" style="background:#0f1623;border:1px solid #2a3448;border-radius:5px;padding:3px 8px;font-size:11px;color:#94a3b8;cursor:pointer;max-width:160px;outline:none">';
    $tzGroups = [
        'UTC' => ['UTC' => 'UTC'],
        'Americas' => [
            'America/New_York'    => 'Eastern (ET)',
            'America/Chicago'     => 'Central (CT)',
            'America/Denver'      => 'Mountain (MT)',
            'America/Phoenix'     => 'Arizona (MST)',
            'America/Los_Angeles' => 'Pacific (PT)',
            'America/Anchorage'   => 'Alaska (AKT)',
            'America/Honolulu'    => 'Hawaii (HST)',
            'America/Toronto'     => 'Toronto',
            'America/Vancouver'   => 'Vancouver',
            'America/Sao_Paulo'   => 'São Paulo',
            'America/Buenos_Aires'=> 'Buenos Aires',
            'America/Mexico_City' => 'Mexico City',
        ],
        'Europe' => [
            'Europe/London'   => 'London (GMT/BST)',
            'Europe/Paris'    => 'Paris (CET)',
            'Europe/Berlin'   => 'Berlin (CET)',
            'Europe/Madrid'   => 'Madrid (CET)',
            'Europe/Rome'     => 'Rome (CET)',
            'Europe/Amsterdam'=> 'Amsterdam (CET)',
            'Europe/Stockholm'=> 'Stockholm (CET)',
            'Europe/Helsinki' => 'Helsinki (EET)',
            'Europe/Athens'   => 'Athens (EET)',
            'Europe/Moscow'   => 'Moscow (MSK)',
            'Europe/Istanbul' => 'Istanbul (TRT)',
        ],
        'Asia & Middle East' => [
            'Asia/Dubai'      => 'Dubai (GST)',
            'Asia/Kolkata'    => 'India (IST)',
            'Asia/Dhaka'      => 'Dhaka (BST)',
            'Asia/Bangkok'    => 'Bangkok (ICT)',
            'Asia/Singapore'  => 'Singapore (SGT)',
            'Asia/Shanghai'   => 'China (CST)',
            'Asia/Hong_Kong'  => 'Hong Kong (HKT)',
            'Asia/Tokyo'      => 'Tokyo (JST)',
            'Asia/Seoul'      => 'Seoul (KST)',
        ],
        'Africa & Pacific' => [
            'Africa/Cairo'       => 'Cairo (EET)',
            'Africa/Nairobi'     => 'Nairobi (EAT)',
            'Africa/Johannesburg'=> 'Johannesburg (SAST)',
            'Pacific/Auckland'   => 'Auckland (NZST)',
            'Australia/Sydney'   => 'Sydney (AEST)',
            'Australia/Melbourne'=> 'Melbourne (AEST)',
        ],
    ];
    foreach ($tzGroups as $groupLabel => $tzMap) {
        if ($groupLabel === 'UTC') {
            echo '<option value="UTC">UTC</option>';
        } else {
            echo '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
            foreach ($tzMap as $tzId => $tzLabel) {
                echo '<option value="' . htmlspecialchars($tzId) . '">' . htmlspecialchars($tzLabel) . '</option>';
            }
            echo '</optgroup>';
        }
    }
    echo '</select>';
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
        /* ── Nav ────────────────────────────────────────────────────────── */
        nav { background: #1a2133; border-bottom: 1px solid #2a3448; display: flex; align-items: center; padding: 0 24px; min-height: 54px; gap: 2px; flex-wrap: wrap; }
        .nav-brand { display: flex; align-items: center; gap: 8px; margin-right: 16px; text-decoration: none; padding: 8px 4px; flex-shrink: 0; }
        .nav-brand-icon { width: 28px; height: 28px; background: #4f8ef7; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-brand-icon svg { width: 15px; height: 15px; fill: #fff; }
        .nav-brand-text { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        nav a { text-decoration: none; font-size: 13px; color: #7a8fa6; padding: 6px 10px; border-radius: 6px; transition: background 0.12s, color 0.12s; white-space: nowrap; }
        nav a:hover { background: #232d42; color: #c8d6e8; }
        nav a.active { background: #1e2d4a; color: #4f8ef7; font-weight: 500; }
        .nav-right { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: nowrap; }
        .nav-user { font-size: 12px; color: #5a7090; white-space: nowrap; }
        .nav-role { background: #2a3448; border-radius: 4px; padding: 1px 6px; font-size: 11px; color: #7a8fa6; }
        nav a.logout { color: #f87171; }
        nav a.logout:hover { background: #2d1a1a; color: #f87171; }

        /* ── Layout ─────────────────────────────────────────────────────── */
        .content { padding: 28px 32px; max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 20px; font-weight: 600; color: #f0f4ff; margin-bottom: 4px; }
        .page-sub { font-size: 13px; color: #5a7090; margin-bottom: 24px; }

        /* ── Cards ──────────────────────────────────────────────────────── */
        .card { background: #1a2133; border: 1px solid #2a3448; border-radius: 10px; padding: 24px; min-width: 0;}
        .card-title { font-size: 14px; font-weight: 600; color: #e2e8f0; margin-bottom: 4px; }
        .card-sub { font-size: 12px; color: #5a7090; margin-bottom: 16px; }

        /* ── Charts grid ────────────────────────────────────────────────── */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;}
        .charts-grid.three { grid-template-columns: repeat(3, 1fr); }

        /* ── Tables (scrollable wrapper) ────────────────────────────────── */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 480px; }
        th { text-align: left; padding: 10px 12px; background: #131d2e; color: #7a8fa6; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #2a3448; white-space: nowrap; }
        td { padding: 9px 12px; border-bottom: 1px solid #1e2a3a; color: #c8d6e8; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #1e2a3a; }

        /* ── Buttons ────────────────────────────────────────────────────── */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: background 0.12s; white-space: nowrap; }
        .btn-primary { background: #4f8ef7; color: #fff; }
        .btn-primary:hover { background: #3b7de8; }
        .btn-secondary { background: #2a3448; color: #c8d6e8; }
        .btn-secondary:hover { background: #344057; }
        .btn-danger { background: #7c2d2d; color: #f87171; }
        .btn-danger:hover { background: #9b3333; }

        /* ── Badges ─────────────────────────────────────────────────────── */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        .badge-visitor { background: #1a3a5c; color: #60a5fa; }
        .badge-performance { background: #1a3a2c; color: #34d399; }
        .badge-behavioral { background: #3a2a1a; color: #fbbf24; }
        .badge-super_admin { background: #3a1a3a; color: #e879f9; }
        .badge-analyst { background: #1a2a3a; color: #60a5fa; }
        .badge-viewer { background: #2a2a1a; color: #fbbf24; }

        /* ── Forms ──────────────────────────────────────────────────────── */
        textarea { width: 100%; background: #0f1623; border: 1px solid #2a3448; border-radius: 6px; padding: 10px 13px; font-size: 13px; color: #e2e8f0; outline: none; resize: vertical; min-height: 100px; font-family: system-ui, -apple-system, sans-serif; box-sizing: border-box; }
        textarea:focus { border-color: #4f8ef7; }
        .alert { padding: 10px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #1a3a2c; border: 1px solid #2d6a4a; color: #34d399; }
        .alert-error { background: #2d1a1a; border: 1px solid #7c2d2d; color: #f87171; }

        /* ── Responsive breakpoints ─────────────────────────────────────── */
        @media (max-width: 900px) {
            .charts-grid, .charts-grid.three { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            nav { padding: 8px 16px; gap: 2px; }
            .nav-brand { margin-right: 8px; }
            .nav-right { margin-left: 0; width: 100%; padding: 4px 0 6px; border-top: 1px solid #2a3448; justify-content: flex-start; }
            .content { padding: 16px; }
            h1 { font-size: 18px; }
            .card { padding: 16px; }
            .charts-grid { gap: 14px; }
        }
        /* KPI stat grid — fluid, auto-wraps */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .kpi-card { background: #131d2e; border: 1px solid #2a3448; border-radius: 8px; padding: 14px 16px; }
        .kpi-label { font-size: 11px; color: #5a7090; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .kpi-value { font-size: 22px; font-weight: 700; }

        @media (max-width: 480px) {
            nav a { font-size: 12px; padding: 5px 8px; }
            .nav-brand-text { display: none; }
            .content { padding: 12px; }
            .card { padding: 12px; }
            .btn { padding: 7px 12px; font-size: 12px; }
            .kpi-grid { gap: 10px; }
        }
        ' . $extraCss . '
    </style>
    <script>
    // ── Timezone conversion ────────────────────────────────────────────────
    const _TZ_KEY = "analytics_tz";
    function _getTZ() {
        return localStorage.getItem(_TZ_KEY) ||
               (typeof Intl !== "undefined" ? Intl.DateTimeFormat().resolvedOptions().timeZone : "UTC");
    }
    function _fmtTs(isoStr, tz) {
        try {
            const d = new Date(isoStr);
            if (isNaN(d)) return isoStr;
            return d.toLocaleString("en-US", {
                timeZone: tz, year: "numeric", month: "short",
                day: "numeric", hour: "2-digit", minute: "2-digit", hour12: false
            });
        } catch(e) { return isoStr; }
    }
    function _applyTZ() {
        const tz = _getTZ();
        document.querySelectorAll("[data-ts]").forEach(el => {
            el.textContent = _fmtTs(el.getAttribute("data-ts"), tz);
        });
        // Update any tz-label spans
        const shortLabel = tz.split("/").pop().replace(/_/g," ");
        document.querySelectorAll("[data-tz-label]").forEach(el => { el.textContent = shortLabel; });
        // Update table headers
        document.querySelectorAll("[data-tz-header]").forEach(el => { el.textContent = "Timestamp (" + shortLabel + ")"; });
    }
    document.addEventListener("DOMContentLoaded", function() {
        const sel = document.getElementById("tz-select");
        if (!sel) return;
        const savedTZ = _getTZ();
        // Find exact match in options; fallback to UTC
        const hasOpt = Array.from(sel.options).some(function(o) { return o.value === savedTZ; });
        sel.value = hasOpt ? savedTZ : "UTC";
        sel.addEventListener("change", function(e) {
            localStorage.setItem(_TZ_KEY, e.target.value);
            _applyTZ();
        });
        _applyTZ();
    });
    </script>
</head>
<body>
';
}
