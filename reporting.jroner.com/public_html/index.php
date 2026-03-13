<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = getCurrentUser();
$role = $user['role'];

// Redirect to best landing page based on role
if ($role === 'viewer') {
    header('Location: /reports/saved.php');
    exit;
}
if ($role === 'super_admin' || in_array('visitor', $user['sections'] ?? [])) {
    header('Location: /reports/visitor.php');
    exit;
}
if (in_array('performance', $user['sections'] ?? [])) {
    header('Location: /reports/performance.php');
    exit;
}
if (in_array('behavioral', $user['sections'] ?? [])) {
    header('Location: /reports/behavioral.php');
    exit;
}
// fallback
header('Location: /reports/saved.php');
exit;
