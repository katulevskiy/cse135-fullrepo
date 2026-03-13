<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
set_time_limit(60); // AI calls can be slow

$user = getCurrentUser();

// ── Parse request (POST JSON or GET fallback) ──────────────────────────────
$isPost      = $_SERVER['REQUEST_METHOD'] === 'POST';
$body        = $isPost ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

$section     = $body['section']     ?? ($_GET['section'] ?? '');
$includeData = $body['includeData'] ?? true;
$aiAnalysis  = $body['aiAnalysis']  ?? false;
$chartImages = $body['charts']      ?? []; // [{label, imageData}]
$reportId    = (int)($_GET['report_id'] ?? 0);

$validSecs = ['visitor', 'performance', 'behavioral'];
if (!in_array($section, $validSecs)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid section']);
    exit;
}

// Auth checks
if ($user['role'] === 'viewer') {
    echo json_encode(['ok' => false, 'error' => 'Viewers cannot export']);
    exit;
}
if ($user['role'] === 'analyst' && !in_array($section, $user['sections'] ?? [])) {
    echo json_encode(['ok' => false, 'error' => 'No section access']);
    exit;
}

$db   = getDb();
$tz   = date('T');
$rows = [];
$headers = [];
$stats   = [];
$aiSummary = '';

// ── Fetch data ──────────────────────────────────────────────────────────────
if ($section === 'visitor') {
    $title  = 'Visitor & Session Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, language, screen, network, source_ip, saved_at
        FROM `static` ORDER BY saved_at DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ["Timestamp ($tz)", 'Session ID', 'Page', 'Language', 'Screen', 'Network', 'IP'];
    $langCounts = [];
    $pageCounts = [];
    $netCounts  = [];
    foreach ($dbRows as $r) {
        $scr  = json_decode($r['screen'] ?? '{}', true);
        $net  = json_decode($r['network'] ?? '{}', true);
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $lang = $r['language'] ?: 'unknown';
        $eff  = $net['effectiveType'] ?? 'unknown';
        $langCounts[$lang] = ($langCounts[$lang] ?? 0) + 1;
        $pageCounts[$path] = ($pageCounts[$path] ?? 0) + 1;
        $netCounts[$eff]   = ($netCounts[$eff]   ?? 0) + 1;
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            $lang,
            ($scr['width'] ?? '?') . 'x' . ($scr['height'] ?? '?'),
            $eff,
            $r['source_ip'],
        ];
    }
    arsort($langCounts); arsort($pageCounts); arsort($netCounts);
    $topLangs  = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($langCounts, 0, 5)), array_slice($langCounts, 0, 5)));
    $topPages  = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($pageCounts, 0, 5)), array_slice($pageCounts, 0, 5)));
    $topNet    = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys($netCounts), $netCounts));
    $stats = ["Total Records: " . count($dbRows)];
    $aiSummary = "Section: Visitor & Session Analytics\nTotal records: " . count($dbRows) .
        "\nTop languages: $topLangs\nTop pages: $topPages\nNetwork types: $topNet";

} elseif ($section === 'performance') {
    $title  = 'Performance Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, total_load_time, saved_at
        FROM performance ORDER BY total_load_time DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ["Timestamp ($tz)", 'Session ID', 'Page', 'Load Time (ms)'];
    $allTimes = array_column($dbRows, 'total_load_time');
    $total    = count($dbRows);
    $avg      = $total ? round(array_sum($allTimes) / $total) : 0;
    $max      = $total ? max($allTimes) : 0;

    // Median
    $sortedTimes = $allTimes;
    sort($sortedTimes);
    $mid    = intdiv($total, 2);
    $median = $total ? ($total % 2 === 0 ? (int)(($sortedTimes[$mid-1] + $sortedTimes[$mid]) / 2) : $sortedTimes[$mid]) : 0;

    // Page avg breakdown (top 5 slowest)
    $pageLoads = [];
    foreach ($dbRows as $r) {
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $pageLoads[$path][] = (int)$r['total_load_time'];
    }
    $pageAvgs = [];
    foreach ($pageLoads as $p => $times) {
        $pageAvgs[$p] = round(array_sum($times) / count($times));
    }
    arsort($pageAvgs);

    foreach ($dbRows as $r) {
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            number_format((int)$r['total_load_time']),
        ];
    }

    $stats = [
        "Sessions: $total",
        "Avg Load: " . number_format($avg) . "ms",
        "Median Load: " . number_format($median) . "ms",
        "Peak Load: " . number_format($max) . "ms",
    ];

    $topSlowPages = implode(', ', array_map(
        fn($k, $v) => "$k (" . number_format($v) . "ms)",
        array_keys(array_slice($pageAvgs, 0, 5)),
        array_slice($pageAvgs, 0, 5)
    ));
    $aiSummary = "Section: Performance Analytics\nSessions: $total\nAvg load: {$avg}ms\nMedian load: {$median}ms\nPeak load: {$max}ms\nSlowest pages (avg): $topSlowPages";

} elseif ($section === 'behavioral') {
    $title  = 'Behavioral Analytics Report';
    $dbRows = $db->query("
        SELECT session_id, page, event_count, events, saved_at
        FROM activity ORDER BY saved_at DESC LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $headers = ["Timestamp ($tz)", 'Session ID', 'Page', '# Events', 'Types'];
    $eventTypeCounts = [];
    $pageErrCounts   = [];
    foreach ($dbRows as $r) {
        $evs  = json_decode($r['events'] ?? '[]', true) ?: [];
        $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
        foreach ($evs as $ev) {
            $t = $ev['type'] ?? 'unknown';
            $eventTypeCounts[$t] = ($eventTypeCounts[$t] ?? 0) + 1;
            if (in_array($t, ['error', 'unhandledrejection'])) {
                $pageErrCounts[$path] = ($pageErrCounts[$path] ?? 0) + 1;
            }
        }
        $types = implode(', ', array_slice(array_unique(array_column($evs, 'type')), 0, 4));
        $rows[] = [
            date('Y-m-d H:i', strtotime($r['saved_at'])),
            substr($r['session_id'], 0, 12) . '...',
            $path,
            $r['event_count'],
            $types,
        ];
    }
    arsort($eventTypeCounts);
    $totalEvents = array_sum($eventTypeCounts);
    $topTypes    = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($eventTypeCounts, 0, 8)), array_slice($eventTypeCounts, 0, 8)));
    $errStr      = $pageErrCounts ? implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys($pageErrCounts), $pageErrCounts)) : 'none';
    $stats = ["Batches: " . count($dbRows), "Total events: " . number_format($totalEvents)];
    $aiSummary = "Section: Behavioral Analytics\nActivity batches: " . count($dbRows) .
        "\nTotal events: $totalEvents\nEvent types: $topTypes\nJS errors by page: $errStr";
}

// ── Load analyst comment ────────────────────────────────────────────────────
$comment = '';
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section=? AND created_by=? AND name='_comment' LIMIT 1");
$stmt->bind_param("si", $section, $user['id']);
$stmt->execute();
$cr = $stmt->get_result()->fetch_assoc();
if ($cr) $comment = $cr['analyst_comments'];

// ── Optional: AI Analytics via OpenRouter ──────────────────────────────────
$aiText = '';
if ($aiAnalysis) {
    $aiText = callOpenRouter($section, $aiSummary, $comment);
}

function callOpenRouter(string $section, string $summary, string $analystNote): string {
    $prompt = "You are an expert web analytics analyst reviewing data for a university course project (CSE 135 Online Measurement and Data Analytics).\n\n"
        . "Analyze the following data and provide:\n"
        . "1. Key insights and patterns you observe\n"
        . "2. Notable anomalies or concerning metrics with likely causes\n"
        . "3. Actionable recommendations for the site owner\n\n"
        . "Data Summary:\n$summary\n";
    if (trim($analystNote)) {
        $prompt .= "\nAnalyst's Note: $analystNote\n";
    }
    $prompt .= "\nKeep your response concise (300-500 words). Use plain text without markdown.";

    $payload = json_encode([
        'model'      => 'openai/gpt-4o-mini',
        'max_tokens' => 600,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    // Use file_get_contents + stream context (curl not available on this server)
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                'Authorization: Bearer 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',
                'HTTP-Referer: https://reporting.jroner.com',
                'X-Title: CSE135 Analytics',
                'Content-Length: ' . strlen($payload),
            ]),
            'content'       => $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents('https://openrouter.ai/api/v1/chat/completions', false, $ctx);
    if ($response === false) return "(AI analysis unavailable: network error)";
    $decoded = json_decode($response, true);
    return $decoded['choices'][0]['message']['content'] ?? "(AI analysis unavailable: unexpected response from API)";
}

// ── Generate PDF ────────────────────────────────────────────────────────────
define('FPDF_FONTPATH', __DIR__ . '/../lib/font/');
require_once __DIR__ . '/../lib/fpdf.php';

class ReportPDF extends FPDF {
    public string $reportTitle = '';
    public string $generatedBy = '';
    public string $tz          = 'UTC';

    function Header(): void {
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(30, 50, 80);
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 120, 140);
        $this->Cell(0, 5, 'Generated: ' . date('F j, Y \a\t H:i') . ' ' . $this->tz . '  |  By: ' . $this->generatedBy, 0, 1, 'L');
        $this->SetDrawColor(60, 100, 160);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer(): void {
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
        $perRow = 3;
        $colW   = 190 / $perRow;
        foreach (array_chunk($stats, $perRow) as $chunk) {
            foreach ($chunk as $s) {
                $this->Cell($colW, 6, $s, 0, 0, 'L');
            }
            $this->Ln(7);
        }
        $this->Ln(3);
    }

    function DataTable(array $headers, array $rows): void {
        if (!$headers || !$rows) return;
        $pageW = 190;
        $colW  = $pageW / count($headers);

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(220, 230, 245);
        $this->SetTextColor(40, 60, 90);
        foreach ($headers as $h) {
            $this->Cell($colW, 7, $h, 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('Helvetica', '', 8);
        $fill = false;
        foreach ($rows as $row) {
            if ($this->GetY() > 270) $this->AddPage();
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
        $this->MultiCell(0, 5, iconv('UTF-8', 'latin1//TRANSLIT', $text), 1, 'L', true);
        $this->Ln(4);
    }

    function AISectionBox(string $text): void {
        if (!trim($text)) return;
        $this->AddPage();
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(60, 30, 120);
        $this->Cell(0, 8, 'AI Analytics (OpenRouter / GPT-4o mini)', 0, 1);
        $this->SetDrawColor(120, 60, 200);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(40, 30, 70);
        $this->SetFillColor(248, 245, 255);
        $this->MultiCell(0, 5, iconv('UTF-8', 'latin1//TRANSLIT', $text), 1, 'L', true);
    }

    function ChartImage(string $tmpPath, string $label, int $width = 180): void {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(50, 60, 80);
        $this->Cell(0, 6, $label, 0, 1);
        if ($this->GetY() + 60 > 270) $this->AddPage();
        $this->Image($tmpPath, 10, null, $width);
        $this->Ln(4);
    }
}

$pdf = new ReportPDF();
$pdf->reportTitle = $title;
$pdf->generatedBy = $user['username'];
$pdf->tz          = $tz;
$pdf->SetMargins(10, 22, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Summary stats
if ($stats) {
    $pdf->SectionTitle('Summary');
    $pdf->StatsRow($stats);
}

// ── Charts (browser-captured images) ───────────────────────────────────────
$tmpFiles = [];
if (!empty($chartImages)) {
    $pdf->SectionTitle('Charts');
    foreach ($chartImages as $chart) {
        $label     = $chart['label']     ?? 'Chart';
        $imageData = $chart['imageData'] ?? '';
        if (!$imageData) continue;

        // Strip data URI prefix (image/jpeg or image/png)
        $base64 = preg_replace('/^data:image\/[a-z]+;base64,/', '', $imageData);
        $binary = base64_decode($base64);
        if (!$binary || strlen($binary) < 200) continue;

        // Detect type from header bytes
        $ext     = (substr($binary, 0, 3) === "\xff\xd8\xff") ? 'jpg' : 'png';
        $tmpFile = tempnam(sys_get_temp_dir(), 'chart_') . '.' . $ext;
        file_put_contents($tmpFile, $binary);
        $tmpFiles[] = $tmpFile;

        $pdf->ChartImage($tmpFile, $label);
    }
}

// ── Raw data table ─────────────────────────────────────────────────────────
if ($includeData && $rows && $headers) {
    if (!empty($chartImages)) $pdf->AddPage();
    $pdf->SectionTitle('Raw Data (' . count($rows) . ' records)');
    $pdf->DataTable($headers, array_slice($rows, 0, 80));
}

// ── Analyst commentary ─────────────────────────────────────────────────────
if ($comment) {
    $pdf->CommentBox($comment);
}

// ── AI section ─────────────────────────────────────────────────────────────
if ($aiText) {
    $pdf->AISectionBox($aiText);
}

// ── Save file ──────────────────────────────────────────────────────────────
$exportsDir = __DIR__ . '/../exports/';
if (!is_dir($exportsDir)) mkdir($exportsDir, 0755, true);

$filename = $section . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.pdf';
$filepath = $exportsDir . $filename;
$pdf->Output('F', $filepath);

// Clean up temp chart image files
foreach ($tmpFiles as $f) {
    if (file_exists($f)) unlink($f);
}

// Update report record if report_id provided
if ($reportId) {
    $stmt2 = $db->prepare("UPDATE saved_reports SET pdf_path=? WHERE id=?");
    $stmt2->bind_param("si", $filename, $reportId);
    $stmt2->execute();
}

$scheme = 'https';
$host   = $_SERVER['HTTP_HOST'] ?? 'reporting.jroner.com';
echo json_encode(['ok' => true, 'url' => "$scheme://$host/exports/$filename", 'path' => $filename]);
