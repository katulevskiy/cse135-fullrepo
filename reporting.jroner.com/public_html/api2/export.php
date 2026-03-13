<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$section   = $_GET['section'] ?? '';
$reportId  = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
$validSecs = ['visitor', 'performance', 'behavioral'];

if (!in_array($section, $validSecs)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid section']);
    exit;
}

$user = getCurrentUser();

// Check access
if ($user['role'] === 'viewer') {
    echo json_encode(['ok' => false, 'error' => 'Viewers cannot export']);
    exit;
}
if ($user['role'] === 'analyst' && !in_array($section, $user['sections'] ?? [])) {
    echo json_encode(['ok' => false, 'error' => 'No section access']);
    exit;
}

$db = getDb();

// Build report data
$title    = '';
$rows     = [];
$headers  = [];
$comment  = '';
$stats    = [];

if ($section === 'visitor') {
    $title = 'Visitor & Session Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, language, screen, network, source_ip, saved_at
        FROM `static` ORDER BY saved_at DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ['Timestamp', 'Session ID', 'Page', 'Language', 'Screen', 'Network', 'IP'];
    foreach ($dbRows as $r) {
        $scr = json_decode($r['screen'] ?? '{}', true);
        $net = json_decode($r['network'] ?? '{}', true);
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            $r['language'] ?: '—',
            ($scr['width'] ?? '?') . 'x' . ($scr['height'] ?? '?'),
            $net['effectiveType'] ?? '—',
            $r['source_ip'],
        ];
    }
    $total = count($dbRows);
    $stats = ["Total Records: $total"];

} elseif ($section === 'performance') {
    $title = 'Performance Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, total_load_time, saved_at
        FROM performance ORDER BY total_load_time DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ['Timestamp', 'Session ID', 'Page', 'Load Time (ms)'];
    foreach ($dbRows as $r) {
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            number_format((int)$r['total_load_time']),
        ];
    }
    $total = count($dbRows);
    $avg = $total ? round(array_sum(array_column($dbRows, 'total_load_time')) / $total) : 0;
    $max = $total ? max(array_column($dbRows, 'total_load_time')) : 0;
    $stats = ["Total Sessions: $total", "Avg Load: " . number_format($avg) . "ms", "Peak Load: " . number_format($max) . "ms"];

} elseif ($section === 'behavioral') {
    $title = 'Behavioral Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, event_count, events, saved_at
        FROM activity ORDER BY saved_at DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ['Timestamp', 'Session ID', 'Page', '# Events', 'Types'];
    foreach ($dbRows as $r) {
        $evs = json_decode($r['events'] ?? '[]', true) ?: [];
        $types = implode(', ', array_slice(array_unique(array_column($evs, 'type')), 0, 4));
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            $r['event_count'],
            $types,
        ];
    }
    $totalEvents = array_sum(array_column($dbRows, 'event_count'));
    $stats = ["Activity Batches: " . count($dbRows), "Total Events: " . number_format($totalEvents)];
}

// Load analyst comment
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section=? AND created_by=? AND name='_comment' LIMIT 1");
$stmt->bind_param("si", $section, $user['id']);
$stmt->execute();
$cr = $stmt->get_result()->fetch_assoc();
if ($cr) $comment = $cr['analyst_comments'];

// Generate PDF using FPDF
define('FPDF_FONTPATH', __DIR__ . '/../lib/font/');
require_once __DIR__ . '/../lib/fpdf.php';

class ReportPDF extends FPDF {
    public string $reportTitle = '';
    public string $generatedBy = '';

    function Header() {
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(30, 50, 80);
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 120, 140);
        $this->Cell(0, 6, 'Generated: ' . date('F j, Y \a\t H:i') . ' | By: ' . $this->generatedBy, 0, 1, 'L');
        $this->SetDrawColor(60, 100, 160);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(140, 160, 180);
        $this->Cell(0, 6, 'CSE 135 Analytics Platform | Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function SectionTitle(string $text): void {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(50, 100, 200);
        $this->Cell(0, 8, $text, 0, 1, 'L');
        $this->SetDrawColor(50, 100, 200);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }

    function StatsRow(array $stats): void {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(60, 80, 100);
        foreach ($stats as $s) {
            $this->Cell(60, 6, $s, 0, 0, 'L');
        }
        $this->Ln(8);
    }

    function DataTable(array $headers, array $rows): void {
        $pageW = 190;
        $colW = $pageW / count($headers);

        // Header row
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(220, 230, 245);
        $this->SetTextColor(40, 60, 90);
        foreach ($headers as $h) {
            $this->Cell($colW, 7, $h, 1, 0, 'C', true);
        }
        $this->Ln();

        // Data rows
        $this->SetFont('Helvetica', '', 8);
        $fill = false;
        foreach ($rows as $row) {
            if ($this->GetY() > 270) { $this->AddPage(); }
            $this->SetFillColor($fill ? 240 : 255, $fill ? 244 : 255, $fill ? 250 : 255);
            $this->SetTextColor(40, 60, 90);
            foreach ($row as $cell) {
                $this->Cell($colW, 6, iconv('UTF-8', 'latin1//TRANSLIT', (string)$cell), 1, 0, 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Ln(4);
    }

    function CommentBox(string $text): void {
        if (!trim($text)) return;
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(50, 100, 200);
        $this->Cell(0, 7, 'Analyst Commentary', 0, 1);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(50, 60, 80);
        $this->SetFillColor(245, 248, 255);
        $clean = iconv('UTF-8', 'latin1//TRANSLIT', $text);
        $this->MultiCell(0, 5, $clean, 1, 'L', true);
        $this->Ln(4);
    }
}

$pdf = new ReportPDF();
$pdf->reportTitle = $title;
$pdf->generatedBy = $user['username'];
$pdf->SetMargins(10, 20, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Stats summary
if ($stats) {
    $pdf->SectionTitle('Summary');
    $pdf->StatsRow($stats);
}

// Data table
$pdf->SectionTitle('Data (' . count($rows) . ' records)');
if ($rows) {
    $pdf->DataTable($headers, array_slice($rows, 0, 80));
} else {
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 6, 'No data available.', 0, 1);
    $pdf->Ln(4);
}

// Analyst comment
if ($comment) {
    $pdf->CommentBox($comment);
}

// Save file
$exportsDir = __DIR__ . '/../exports/';
if (!is_dir($exportsDir)) mkdir($exportsDir, 0755, true);

$filename = $section . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.pdf';
$filepath = $exportsDir . $filename;
$pdf->Output('F', $filepath);

$scheme = 'https';
$host   = $_SERVER['HTTP_HOST'] ?? 'reporting.jroner.com';
$url    = "$scheme://$host/exports/$filename";

// Update report record if report_id provided
if ($reportId) {
    $stmt = $db->prepare("UPDATE saved_reports SET pdf_path=? WHERE id=?");
    $stmt->bind_param("si", $filename, $reportId);
    $stmt->execute();
}

echo json_encode(['ok' => true, 'url' => $url, 'path' => $filename]);
