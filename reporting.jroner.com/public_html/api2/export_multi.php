<?php
/**
 * Multi-section PDF export endpoint.
 * Accepts POST JSON: {
 *   sections: [{section, charts:[{label,imageData}], comment, includeData}],
 *   reportId: int,
 *   title: string,
 *   aiAnalysis: bool
 * }
 */
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
set_time_limit(120);

$user = getCurrentUser();
$db   = getDb();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$sections   = $body['sections']   ?? [];
$reportId   = (int)($body['reportId']  ?? 0);
$title      = trim($body['title']      ?? 'Analytics Report');
$aiAnalysis = (bool)($body['aiAnalysis'] ?? false);

if (!$sections || !is_array($sections)) {
    echo json_encode(['ok' => false, 'error' => 'No sections provided']); exit;
}
if ($user['role'] === 'viewer') {
    echo json_encode(['ok' => false, 'error' => 'Viewers cannot export']); exit;
}

$validSecs = ['visitor', 'performance', 'behavioral'];
foreach ($sections as $sec) {
    $sk = $sec['section'] ?? '';
    if (!in_array($sk, $validSecs)) {
        echo json_encode(['ok' => false, 'error' => "Invalid section: $sk"]); exit;
    }
    if ($user['role'] === 'analyst' && !in_array($sk, $user['sections'] ?? [])) {
        echo json_encode(['ok' => false, 'error' => "No access to section: $sk"]); exit;
    }
}

$tz = date('T');

// ── Per-section data fetching ─────────────────────────────────────────────
function fetchSectionData(string $key, $db, string $tz): array {
    $rows = []; $headers = []; $stats = []; $aiSummary = '';
    $sectionTitle = '';
    $fpData = null; // visitor only

    if ($key === 'visitor') {
        $sectionTitle = 'Visitor & Session Analytics';
        $dbRows = $db->query("
            SELECT session_id, page, language, screen, network, source_ip, saved_at
            FROM `static` ORDER BY saved_at DESC LIMIT 100
        ")->fetch_all(MYSQLI_ASSOC);

        $headers = ["Timestamp ($tz)", 'Session ID', 'Page', 'Language', 'Screen', 'Network', 'IP'];
        $langCounts = []; $pageCounts = []; $netCounts = [];
        foreach ($dbRows as $r) {
            $scr  = json_decode($r['screen']  ?? '{}', true);
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
                $path, $lang,
                ($scr['width'] ?? '?') . 'x' . ($scr['height'] ?? '?'),
                $eff, $r['source_ip'],
            ];
        }
        arsort($langCounts); arsort($pageCounts); arsort($netCounts);
        $topLangs = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($langCounts,0,5)), array_slice($langCounts,0,5)));
        $topPages = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($pageCounts,0,5)), array_slice($pageCounts,0,5)));
        $topNet   = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys($netCounts), $netCounts));
        $stats    = ["Total Records: " . count($dbRows)];
        $aiSummary = "Section: Visitor Analytics\nTotal records: " . count($dbRows) .
            "\nTop languages: $topLangs\nTop pages: $topPages\nNetwork types: $topNet";

        // Device fingerprint data
        $fpDbRows = $db->query("
            SELECT canvas_fp, device_type, device_make, os_name, browser_name, browser_ver,
                   cpu_cores, device_mem, touch_points, pixel_ratio, webgl_renderer
            FROM device_fingerprints ORDER BY saved_at DESC LIMIT 200
        ")->fetch_all(MYSQLI_ASSOC);

        if ($fpDbRows) {
            $fpUnique = []; $fpDevTypes = []; $fpBrowsers = []; $fpOS = []; $fpMakes = [];
            foreach ($fpDbRows as $fp) {
                if ($fp['canvas_fp']) $fpUnique[$fp['canvas_fp']] = true;
                $fpDevTypes[$fp['device_type'] ?: 'unknown'] = ($fpDevTypes[$fp['device_type'] ?: 'unknown'] ?? 0) + 1;
                if ($fp['browser_name']) $fpBrowsers[$fp['browser_name']] = ($fpBrowsers[$fp['browser_name']] ?? 0) + 1;
                if ($fp['os_name'])      $fpOS[$fp['os_name']]            = ($fpOS[$fp['os_name']] ?? 0) + 1;
                if ($fp['device_make'])  $fpMakes[$fp['device_make']]     = ($fpMakes[$fp['device_make']] ?? 0) + 1;
            }
            arsort($fpBrowsers); arsort($fpOS); arsort($fpMakes);
            $fpData = compact('fpDbRows','fpUnique','fpDevTypes','fpBrowsers','fpOS','fpMakes');
            $aiSummary .= "\nDevice fingerprints: " . count($fpDbRows) . " sessions, " . count($fpUnique) . " unique" .
                "\nTop browser: " . (array_key_first($fpBrowsers) ?? 'N/A') .
                "\nTop OS: "      . (array_key_first($fpOS) ?? 'N/A');
        }

    } elseif ($key === 'performance') {
        $sectionTitle = 'Performance Analytics';
        $dbRows = $db->query("
            SELECT session_id, page, total_load_time, saved_at
            FROM performance ORDER BY total_load_time DESC LIMIT 100
        ")->fetch_all(MYSQLI_ASSOC);

        $headers   = ["Timestamp ($tz)", 'Session ID', 'Page', 'Load Time (ms)'];
        $allTimes  = array_column($dbRows, 'total_load_time');
        $total     = count($dbRows);
        $avg       = $total ? round(array_sum($allTimes) / $total) : 0;
        $max       = $total ? max($allTimes) : 0;
        $sorted    = $allTimes; sort($sorted);
        $mid       = intdiv($total, 2);
        $median    = $total ? ($total % 2 === 0 ? (int)(($sorted[$mid-1]+$sorted[$mid])/2) : $sorted[$mid]) : 0;
        $pageLoads = [];
        foreach ($dbRows as $r) {
            $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
            $pageLoads[$path][] = (int)$r['total_load_time'];
        }
        $pageAvgs = [];
        foreach ($pageLoads as $p => $ts) $pageAvgs[$p] = round(array_sum($ts)/count($ts));
        arsort($pageAvgs);
        foreach ($dbRows as $r) {
            $path   = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
            $rows[] = [
                date('Y-m-d H:i', strtotime($r['saved_at'])),
                substr($r['session_id'], 0, 12) . '...',
                $path, number_format((int)$r['total_load_time']),
            ];
        }
        $stats = [
            "Sessions: $total",
            "Avg Load: " . number_format($avg) . "ms",
            "Median Load: " . number_format($median) . "ms",
            "Peak Load: " . number_format($max) . "ms",
        ];
        $topSlowPages = implode(', ', array_map(
            fn($k,$v) => "$k (" . number_format($v) . "ms)",
            array_keys(array_slice($pageAvgs,0,5)), array_slice($pageAvgs,0,5)
        ));
        $aiSummary = "Section: Performance Analytics\nSessions: $total\nAvg load: {$avg}ms\n" .
            "Median: {$median}ms\nPeak: {$max}ms\nSlowest pages: $topSlowPages";

    } elseif ($key === 'behavioral') {
        $sectionTitle = 'Behavioral Analytics';
        $dbRows = $db->query("
            SELECT session_id, page, event_count, events, saved_at
            FROM activity ORDER BY saved_at DESC LIMIT 100
        ")->fetch_all(MYSQLI_ASSOC);

        $headers = ["Timestamp ($tz)", 'Session ID', 'Page', '# Events', 'Types'];
        $eventTypeCounts = []; $pageErrCounts = [];
        foreach ($dbRows as $r) {
            $evs  = json_decode($r['events'] ?? '[]', true) ?: [];
            $path = parse_url($r['page'] ?? '', PHP_URL_PATH) ?: '/';
            foreach ($evs as $ev) {
                $t = $ev['type'] ?? 'unknown';
                $eventTypeCounts[$t] = ($eventTypeCounts[$t] ?? 0) + 1;
                if (in_array($t, ['error','unhandledrejection'])) {
                    $pageErrCounts[$path] = ($pageErrCounts[$path] ?? 0) + 1;
                }
            }
            $types  = implode(', ', array_slice(array_unique(array_column($evs, 'type')), 0, 4));
            $rows[] = [
                date('Y-m-d H:i', strtotime($r['saved_at'])),
                substr($r['session_id'], 0, 12) . '...',
                $path, $r['event_count'], $types,
            ];
        }
        arsort($eventTypeCounts);
        $totalEvents = array_sum($eventTypeCounts);
        $topTypes    = implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys(array_slice($eventTypeCounts,0,8)), array_slice($eventTypeCounts,0,8)));
        $errStr      = $pageErrCounts ? implode(', ', array_map(fn($k,$v) => "$k ($v)", array_keys($pageErrCounts), $pageErrCounts)) : 'none';
        $stats       = ["Batches: " . count($dbRows), "Total events: " . number_format($totalEvents)];
        $aiSummary   = "Section: Behavioral Analytics\nActivity batches: " . count($dbRows) .
            "\nTotal events: $totalEvents\nEvent types: $topTypes\nJS errors: $errStr";
    }

    return compact('sectionTitle','rows','headers','stats','aiSummary','fpData');
}

// ── Optional AI analysis ───────────────────────────────────────────────────
function callOpenRouterMulti(string $combinedSummary): string {
    $prompt = "You are an expert web analytics analyst reviewing data for a university course project (CSE 135).\n\n"
        . "Analyze the following multi-section data and provide:\n"
        . "1. Cross-section patterns and correlations\n"
        . "2. Key insights per section\n"
        . "3. Actionable recommendations for the site owner\n\n"
        . "Data:\n$combinedSummary\n\n"
        . "Keep your response concise (400-600 words). Use plain text without markdown.";

    $payload = json_encode([
        'model'      => 'openai/gpt-4o-mini',
        'max_tokens' => 700,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
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
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $response = @file_get_contents('https://openrouter.ai/api/v1/chat/completions', false, $ctx);
    if ($response === false) return "(AI analysis unavailable: network error)";
    $decoded = json_decode($response, true);
    return $decoded['choices'][0]['message']['content'] ?? "(AI analysis unavailable)";
}

// ── Build PDF ─────────────────────────────────────────────────────────────
define('FPDF_FONTPATH', __DIR__ . '/../lib/font/');
require_once __DIR__ . '/../lib/fpdf.php';

class MultiReportPDF extends FPDF {
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

    function SectionHeader(string $text): void {
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetFillColor(230, 238, 255);
        $this->SetTextColor(30, 60, 130);
        $this->Cell(0, 10, $text, 0, 1, 'L', true);
        $this->Ln(2);
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
            foreach ($chunk as $s) $this->Cell($colW, 6, $s, 0, 0, 'L');
            $this->Ln(7);
        }
        $this->Ln(3);
    }

    function DataTable(array $headers, array $rows): void {
        if (!$headers || !$rows) return;
        $colW = 190 / count($headers);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(220, 230, 245);
        $this->SetTextColor(40, 60, 90);
        foreach ($headers as $h) $this->Cell($colW, 7, $h, 1, 0, 'C', true);
        $this->Ln();
        $this->SetFont('Helvetica', '', 8);
        $fill = false;
        foreach ($rows as $row) {
            if ($this->GetY() > 270) $this->AddPage();
            $this->SetFillColor($fill ? 240 : 255, $fill ? 244 : 255, $fill ? 250 : 255);
            $this->SetTextColor(40, 60, 90);
            foreach ($row as $cell) {
                $this->Cell($colW, 6, iconv('UTF-8','latin1//TRANSLIT',(string)$cell), 1, 0, 'L', $fill);
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
        $this->MultiCell(0, 5, iconv('UTF-8','latin1//TRANSLIT',$text), 1, 'L', true);
        $this->Ln(4);
    }

    function ChartRow(array $charts): void {
        if (!$charts) return;
        $n      = count($charts);
        $margin = $this->lMargin;
        $usable = $this->w - $margin - $this->rMargin;
        $gutter = 5;
        $chartW = $n === 1 ? $usable : ($usable - $gutter) / 2;
        $labelH = 6;
        $maxImgH = 50.0;
        foreach ($charts as $c) {
            $info = @getimagesize($c['path']);
            if ($info && $info[0] > 0) {
                $h = $chartW * $info[1] / $info[0];
                if ($h > $maxImgH) $maxImgH = $h;
            }
        }
        $rowH = $labelH + $maxImgH;
        if ($this->GetY() + $rowH > $this->h - $this->bMargin) $this->AddPage();
        $startY = $this->GetY();
        foreach ($charts as $i => $chart) {
            $x = $margin + ($chartW + $gutter) * $i;
            $this->SetFont('Helvetica', 'B', 8);
            $this->SetTextColor(50, 70, 100);
            $this->SetXY($x, $startY);
            $this->Cell($chartW, $labelH, $chart['label'], 0, 0, 'L');
            $this->Image($chart['path'], $x, $startY + $labelH, $chartW);
        }
        $this->SetY($startY + $rowH + 5);
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
        $this->MultiCell(0, 5, iconv('UTF-8','latin1//TRANSLIT',$text), 1, 'L', true);
    }
}

$pdf = new MultiReportPDF();
$pdf->reportTitle = $title ?: 'Analytics Report';
$pdf->generatedBy = $user['username'];
$pdf->tz          = $tz;
$pdf->SetMargins(10, 22, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$tmpFiles    = [];
$aiSummaries = [];
$isFirst     = true;

foreach ($sections as $secInput) {
    $secKey     = $secInput['section'];
    $chartImgs  = $secInput['charts']      ?? [];
    $comment    = trim($secInput['comment']     ?? '');
    $includeData = ($secInput['includeData'] ?? true) !== false;

    // Fetch DB data for this section
    $d = fetchSectionData($secKey, $db, $tz);
    $aiSummaries[] = $d['aiSummary'];

    // Section divider (new page except first)
    if (!$isFirst) $pdf->AddPage();
    $isFirst = false;

    $pdf->SectionHeader($d['sectionTitle']);

    // Analyst commentary first
    if ($comment) {
        $pdf->SectionTitle('Analyst Commentary');
        $pdf->CommentBox($comment);
    }

    // Summary stats
    if ($d['stats']) {
        $pdf->SectionTitle('Summary');
        $pdf->StatsRow($d['stats']);
    }

    // Charts (browser-captured, 2 per row)
    $tmpCharts = [];
    foreach ($chartImgs as $chart) {
        $label     = $chart['label']     ?? 'Chart';
        $imageData = $chart['imageData'] ?? '';
        if (!$imageData) continue;
        $base64 = preg_replace('/^data:image\/[a-z]+;base64,/', '', $imageData);
        $binary = base64_decode($base64);
        if (!$binary || strlen($binary) < 200) continue;
        $ext     = (substr($binary, 0, 3) === "\xff\xd8\xff") ? 'jpg' : 'png';
        $tmpFile = tempnam(sys_get_temp_dir(), 'chart_') . '.' . $ext;
        file_put_contents($tmpFile, $binary);
        $tmpFiles[]  = $tmpFile;
        $tmpCharts[] = ['path' => $tmpFile, 'label' => $label];
    }
    if ($tmpCharts) {
        $pdf->SectionTitle('Charts');
        foreach (array_chunk($tmpCharts, 2) as $pair) $pdf->ChartRow($pair);
    }

    // Raw data table
    if ($includeData && $d['rows'] && $d['headers']) {
        if ($tmpCharts) $pdf->AddPage();
        $pdf->SectionTitle('Raw Data (' . count($d['rows']) . ' records)');
        $pdf->DataTable($d['headers'], array_slice($d['rows'], 0, 80));
    }

    // Device Intelligence (visitor only)
    if ($secKey === 'visitor' && $includeData && $d['fpData']) {
        $fp = $d['fpData'];
        $fpTotal = count($fp['fpDbRows']);
        $pdf->AddPage();
        $pdf->SectionTitle('Device Intelligence (' . $fpTotal . ' fingerprinted sessions)');
        $fpKPIs = [
            'Total Sessions: ' . $fpTotal,
            'Unique Fingerprints: ' . count($fp['fpUnique']),
            'Top Browser: ' . (array_key_first($fp['fpBrowsers']) ?? 'N/A'),
            'Top OS: '      . (array_key_first($fp['fpOS']) ?? 'N/A'),
            'Top Make: '    . (array_key_first($fp['fpMakes']) ?? 'N/A'),
            'Desktop: ' . ($fp['fpDevTypes']['desktop'] ?? 0) . ' / Mobile: ' . ($fp['fpDevTypes']['mobile'] ?? 0),
        ];
        $pdf->StatsRow($fpKPIs);
        $fpHeaders   = ['Canvas FP', 'Type', 'Make', 'OS', 'Browser', 'CPU', 'RAM', 'PxRatio', 'GPU'];
        $fpTableRows = array_map(fn($r) => [
            $r['canvas_fp'] ?? '—',
            $r['device_type'] ?? '—',
            $r['device_make'] ?? '—',
            $r['os_name'] ?? '—',
            ($r['browser_name'] ?? '—') . ' ' . substr($r['browser_ver'] ?? '', 0, 5),
            $r['cpu_cores'] ?? '—',
            $r['device_mem'] ? $r['device_mem'] . 'GB' : '—',
            $r['pixel_ratio'] ? 'x' . $r['pixel_ratio'] : '—',
            substr(preg_replace('/ANGLE \(([^,]+),\s*([^,]+?)(?:\s+\(.*\))?,.*\)/', '$2', $r['webgl_renderer'] ?? '—'), 0, 28),
        ], array_slice($fp['fpDbRows'], 0, 30));
        $pdf->DataTable($fpHeaders, $fpTableRows);
    }
}

// ── Optional AI analysis (combined) ──────────────────────────────────────
if ($aiAnalysis && $aiSummaries) {
    $combinedSummary = implode("\n\n---\n\n", $aiSummaries);
    $aiText = callOpenRouterMulti($combinedSummary);
    if ($aiText) $pdf->AISectionBox($aiText);
}

// ── Save PDF ──────────────────────────────────────────────────────────────
$exportsDir = __DIR__ . '/../exports/';
if (!is_dir($exportsDir)) mkdir($exportsDir, 0755, true);

$slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($title));
$slug     = substr($slug, 0, 30);
$filename = $slug . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.pdf';
$filepath = $exportsDir . $filename;
$pdf->Output('F', $filepath);

foreach ($tmpFiles as $f) { if (file_exists($f)) unlink($f); }

// Update report record
if ($reportId) {
    $stmt = $db->prepare("UPDATE saved_reports SET pdf_path=? WHERE id=?");
    $stmt->bind_param("si", $filename, $reportId);
    $stmt->execute();
}

$scheme = 'https';
$host   = $_SERVER['HTTP_HOST'] ?? 'reporting.jroner.com';
echo json_encode(['ok' => true, 'url' => "$scheme://$host/exports/$filename", 'path' => $filename]);
