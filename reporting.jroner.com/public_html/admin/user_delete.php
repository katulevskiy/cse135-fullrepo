<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireRole('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: /admin/index.php');
    exit;
}

$db = getDb();
$id = (int)$_POST['id'];
$currentUser = getCurrentUser();

// Prevent self-deletion
if ($id === (int)$currentUser['id']) {
    header('Location: /admin/index.php?msg=self_delete_denied');
    exit;
}

$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header('Location: /admin/index.php?msg=deleted');
exit;
