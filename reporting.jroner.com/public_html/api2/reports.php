<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user = getCurrentUser();
$db   = getDb();
$method = $_SERVER['REQUEST_METHOD'];

function jsonOut(bool $ok, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok], $extra));
    exit;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $stmt = $db->prepare("
            SELECT r.*, u.username as creator
            FROM saved_reports r
            JOIN users u ON u.id = r.created_by
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) jsonOut(false, ['error' => 'Not found']);
        jsonOut(true, ['data' => $row]);
    }
    $reports = $db->query("
        SELECT r.id, r.name, r.section, r.analyst_comments, r.html_path, r.pdf_path, r.created_at, u.username as creator
        FROM saved_reports r
        JOIN users u ON u.id = r.created_by
        WHERE r.name != '_comment'
        ORDER BY r.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    jsonOut(true, ['data' => $reports]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $body['action'] ?? '';

    if ($action === 'save_comment') {
        $section  = $body['section'] ?? '';
        $comments = trim($body['analyst_comments'] ?? '');
        $validSec = ['visitor', 'performance', 'behavioral'];
        if (!in_array($section, $validSec)) jsonOut(false, ['error' => 'Invalid section']);
        if ($user['role'] === 'analyst' && !in_array($section, $user['sections'] ?? [])) {
            jsonOut(false, ['error' => 'No section access']);
        }
        if ($user['role'] === 'viewer') jsonOut(false, ['error' => 'Viewers cannot save comments']);

        $stmt = $db->prepare("SELECT id FROM saved_reports WHERE section=? AND created_by=? AND name='_comment' LIMIT 1");
        $stmt->bind_param("si", $section, $user['id']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $db->prepare("UPDATE saved_reports SET analyst_comments=?, created_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $comments, $existing['id']);
        } else {
            $name = '_comment';
            $stmt = $db->prepare("INSERT INTO saved_reports (name, section, analyst_comments, created_by) VALUES (?,?,?,?)");
            $stmt->bind_param("sssi", $name, $section, $comments, $user['id']);
        }
        $stmt->execute();
        if ($db->errno) jsonOut(false, ['error' => $db->error]);
        jsonOut(true, ['message' => 'Comment saved']);
    }

    if ($action === 'save_report') {
        $name     = trim($body['name'] ?? '');
        $section  = $body['section'] ?? '';
        $comments = trim($body['analyst_comments'] ?? '');
        $validSec = ['visitor', 'performance', 'behavioral'];

        if (!$name) jsonOut(false, ['error' => 'Name is required']);
        if (!in_array($section, $validSec)) jsonOut(false, ['error' => 'Invalid section']);
        if ($user['role'] === 'viewer') jsonOut(false, ['error' => 'Viewers cannot save reports']);
        if ($user['role'] === 'analyst' && !in_array($section, $user['sections'] ?? [])) {
            jsonOut(false, ['error' => 'No section access']);
        }

        $stmt = $db->prepare("INSERT INTO saved_reports (name, section, analyst_comments, created_by) VALUES (?,?,?,?)");
        $stmt->bind_param("sssi", $name, $section, $comments, $user['id']);
        $stmt->execute();
        if ($db->errno) jsonOut(false, ['error' => $db->error]);
        $newId = $db->insert_id;

        // Try to generate PDF
        $scheme = 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'reporting.jroner.com';
        $exportUrl = "$scheme://$host/api2/export.php?section=$section&report_id=$newId";

        $ch = curl_init($exportUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIE         => session_name() . '=' . session_id(),
        ]);
        $pdfRes  = curl_exec($ch);
        curl_close($ch);

        $pdfData = $pdfRes ? json_decode($pdfRes, true) : null;
        if (!empty($pdfData['path'])) {
            $pdfPath = $pdfData['path'];
            $stmt2 = $db->prepare("UPDATE saved_reports SET pdf_path=? WHERE id=?");
            $stmt2->bind_param("si", $pdfPath, $newId);
            $stmt2->execute();
        }

        jsonOut(true, ['id' => $newId, 'message' => 'Report saved']);
    }

    jsonOut(false, ['error' => 'Unknown action']);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonOut(false, ['error' => 'ID required']);
    if ($user['role'] === 'viewer') jsonOut(false, ['error' => 'Not allowed']);

    $stmt = $db->prepare("SELECT created_by FROM saved_reports WHERE id=? AND name != '_comment'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) jsonOut(false, ['error' => 'Not found']);

    if ($user['role'] !== 'super_admin' && (int)$row['created_by'] !== (int)$user['id']) {
        jsonOut(false, ['error' => 'Not allowed to delete others\' reports']);
    }

    $stmt = $db->prepare("DELETE FROM saved_reports WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    jsonOut(true, ['message' => 'Report deleted']);
}

jsonOut(false, ['error' => 'Method not allowed']);
