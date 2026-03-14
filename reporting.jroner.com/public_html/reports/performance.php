<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireSection('performance');

$db = getDb();
$tz = date('T'); // server timezone abbreviation for display

$rows = $db->query("
    SELECT session_id, page, total_load_time, timing, saved_at
    FROM performance
    ORDER BY saved_at DESC
    LIMIT 500
")->fetch_all(MYSQLI_ASSOC);

// Aggregates
$pageLoads = [];
$timeSeries = [];
$buckets = ['<100ms' => 0, '100–500ms' => 0, '500ms–1s' => 0, '1–5s' => 0, '>5s' => 0];
$pageTimingAccum = [];

foreach ($rows as $row) {
    $lt   = (int)$row['total_load_time'];
    $path = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';

    $pageLoads[$path][] = $lt;

    $day = date('Y-m-d', strtotime($row['saved_at']));
    $timeSeries[$day][] = $lt;

    if ($lt < 100)       $buckets['<100ms']++;
    elseif ($lt < 500)   $buckets['100–500ms']++;
    elseif ($lt < 1000)  $buckets['500ms–1s']++;
    elseif ($lt < 5000)  $buckets['1–5s']++;
    else                 $buckets['>5s']++;

    $t = json_decode($row['timing'] ?? '{}', true);
    if (!empty($t['responseStart']) && !empty($t['loadEventEnd'])) {
        $pageTimingAccum[$path]['dns'][]  = max(0, ($t['domainLookupEnd'] ?? 0) - ($t['domainLookupStart'] ?? 0));
        $pageTimingAccum[$path]['tcp'][]  = max(0, ($t['connectEnd'] ?? 0) - ($t['connectStart'] ?? 0));
        $pageTimingAccum[$path]['ttfb'][] = max(0, ($t['responseStart'] ?? 0) - ($t['requestStart'] ?? 0));
        $pageTimingAccum[$path]['dom'][]  = max(0, ($t['domComplete'] ?? 0) - ($t['domInteractive'] ?? 0));
        $pageTimingAccum[$path]['load'][] = max(0, ($t['loadEventEnd'] ?? 0) - ($t['loadEventStart'] ?? 0));
    }
}

// Avg + median per page
$avgByPage = [];
foreach ($pageLoads as $path => $times) {
    $avgByPage[$path] = round(array_sum($times) / count($times));
}
arsort($avgByPage);

// Avg daily
$avgDaily = [];
ksort($timeSeries);
foreach ($timeSeries as $day => $times) {
    $avgDaily[$day] = round(array_sum($times) / count($times));
}

// Timing breakdown
$timingPages = [];
$timingDns = $timingTcp = $timingTtfb = $timingDom = $timingLoad = [];
foreach ($pageTimingAccum as $path => $vals) {
    $timingPages[] = $path;
    $avg = fn($arr) => $arr ? round(array_sum($arr) / count($arr), 1) : 0;
    $timingDns[]  = $avg($vals['dns']);
    $timingTcp[]  = $avg($vals['tcp']);
    $timingTtfb[] = $avg($vals['ttfb']);
    $timingDom[]  = $avg($vals['dom']);
    $timingLoad[] = $avg($vals['load']);
}

// Overall stats: avg, median, max
$totalRows  = count($rows);
$allTimes   = array_column($rows, 'total_load_time');
$overallAvg = $totalRows ? round(array_sum($allTimes) / $totalRows) : 0;
$maxLoad    = $totalRows ? max($allTimes) : 0;

// Median calculation
function calcMedian(array $vals): int|float {
    if (!$vals) return 0;
    sort($vals);
    $n   = count($vals);
    $mid = intdiv($n, 2);
    return $n % 2 === 0 ? (int)(($vals[$mid - 1] + $vals[$mid]) / 2) : $vals[$mid];
}
$overallMedian = calcMedian($allTimes);

// Slowest sessions
$slowRows = $db->query("
    SELECT session_id, page, total_load_time, saved_at
    FROM performance
    ORDER BY total_load_time DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Analyst comment
$user    = getCurrentUser();
$comment = '';
$commentName = '_comment';
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section='performance' AND created_by=? AND name=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("is", $user['id'], $commentName);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $comment = $r['analyst_comments'];

// Chart config for export modal
$chartConfig = [
    ['id' => 'avgLoadChart', 'label' => 'Avg Load Time by Page'],
    ['id' => 'dailyChart',   'label' => 'Daily Avg Load Time'],
    ['id' => 'bucketChart',  'label' => 'Load Time Distribution'],
    ['id' => 'timingChart',  'label' => 'Timing Breakdown by Page'],
];
$section = 'performance';

pageHead('Performance Analytics');
renderNav('performance');
?>
<div class="content">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:16px">
        <div>
            <h1>Performance Analytics</h1>
            <p class="page-sub">
                Page load timing across <?= $totalRows ?> sessions —
                avg <?= number_format($overallAvg) ?>ms,
                median <?= number_format($overallMedian) ?>ms,
                peak <?= number_format($maxLoad) ?>ms
                <span style="color:#3d4f66;margin-left:6px">(all times <span data-tz-label><?= $tz ?></span>)</span>
            </p>
        </div>
        <button class="btn btn-secondary" onclick="openExportModal()">Export PDF</button>
    </div>

    <!-- KPI cards -->
    <div class="kpi-grid">
        <?php
        $kpis = [
            ['Avg Load Time',    number_format($overallAvg) . 'ms',    $overallAvg    < 1000 ? '#34d399' : ($overallAvg    < 3000 ? '#fbbf24' : '#f87171')],
            ['Median Load Time', number_format($overallMedian) . 'ms', $overallMedian < 1000 ? '#34d399' : ($overallMedian < 3000 ? '#fbbf24' : '#f87171')],
            ['Peak Load Time',   number_format($maxLoad) . 'ms',       $maxLoad < 2000 ? '#34d399' : '#f87171'],
            ['Sessions',         $totalRows,                            '#4f8ef7'],
        ];
        foreach ($kpis as [$label, $val, $color]): ?>
        <div class="card" style="text-align:center;padding:18px 16px">
            <div style="font-size:26px;font-weight:700;color:<?= $color ?>;margin-bottom:4px"><?= $val ?></div>
            <div style="font-size:11px;color:#7a8fa6"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="charts-grid" style="margin-bottom:20px">
        <div class="card">
            <div class="card-title">Avg Load Time by Page</div>
            <div class="card-sub">Average total_load_time per page path (ms) — note product-detail anomaly from chaos.js</div>
            <canvas id="avgLoadChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Daily Avg Load Time</div>
            <div class="card-sub">Average page load time trend over time (ms)</div>
            <canvas id="dailyChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Load Time Distribution</div>
            <div class="card-sub">Sessions bucketed by load time — most pages load fast except outliers</div>
            <canvas id="bucketChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Timing Breakdown by Page</div>
            <div class="card-sub">Avg DNS + TCP + TTFB + DOM + Load stacked per page (ms)</div>
            <canvas id="timingChart" height="240"></canvas>
        </div>
    </div>

    <!-- Slowest sessions table -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-title" style="margin-bottom:16px">Slowest Sessions (Top 20)</div>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Load Time</th>
                    <th>Page</th>
                    <th>Session ID</th>
                    <th data-tz-header>Recorded At (<?= $tz ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slowRows as $row):
                    $lt    = (int)$row['total_load_time'];
                    $clr   = $lt < 500 ? '#34d399' : ($lt < 2000 ? '#fbbf24' : '#f87171');
                    $path  = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';
                ?>
                <tr>
                    <td style="font-weight:600;color:<?= $clr ?>;white-space:nowrap"><?= number_format($lt) ?>ms</td>
                    <td title="<?= htmlspecialchars($row['page'] ?? '') ?>"><?= htmlspecialchars($path) ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#5a7090"><?= htmlspecialchars(substr($row['session_id'], 0, 14)) ?>…</td>
                    <td style="font-size:12px;color:#7a8fa6;white-space:nowrap" data-ts="<?= date('Y-m-d\TH:i:s\Z', strtotime($row['saved_at'])) ?>"><?= date('M j, Y H:i', strtotime($row['saved_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Analyst Commentary -->
    <div class="card">
        <div class="card-title" style="margin-bottom:4px">Analyst Commentary</div>
        <div class="card-sub">Interpret performance data — note anomalies, root causes, recommendations.</div>
        <div style="margin-bottom:10px"></div>
        <?php
        if (!$comment) {
            $slowest    = array_key_first($avgByPage) ?: 'N/A';
            $slowestAvg = reset($avgByPage) ?: 0;
            $fastest    = array_key_last($avgByPage)  ?: 'N/A';
            $fastestAvg = end($avgByPage)             ?: 0;
            $pagesCount = count($avgByPage);
            $slowPct    = $totalRows ? round($buckets['>5s'] / $totalRows * 100) : 0;
            $fastPct    = $totalRows ? round(($buckets['<100ms'] + $buckets['100–500ms']) / $totalRows * 100) : 0;
            $comment    = "Performance Analysis — " . date('F j, Y') . "\n\n"
                . "Dataset: $totalRows sessions across $pagesCount page paths.\n"
                . "Overall avg load: " . number_format($overallAvg) . "ms | Median: " . number_format($overallMedian) . "ms | Peak: " . number_format($maxLoad) . "ms.\n\n"
                . "Outlier alert: The avg is " . ($overallAvg > $overallMedian * 1.5 ? 'significantly' : 'moderately') . " higher than the median, "
                . "indicating heavy-tailed distribution driven by outliers rather than typical session performance.\n\n"
                . "Slowest page: $slowest (avg " . number_format($slowestAvg) . "ms) — likely affected by chaos.js or other blocking resources.\n"
                . "Fastest page: $fastest (avg " . number_format($fastestAvg) . "ms).\n\n"
                . "Load distribution: {$slowPct}% of sessions exceed 5s (poor UX threshold). "
                . "{$fastPct}% load under 500ms (excellent). "
                . "Priority: investigate resource-blocking scripts on $slowest.\n\n"
                . "Recommendation: Implement resource hints (preload, prefetch), lazy-load below-fold assets, "
                . "and audit third-party scripts. Removing chaos.js from production is the single highest-impact fix.";
        }
        ?>
        <textarea id="commentBox" placeholder="Analyst commentary for performance data..."><?= htmlspecialchars($comment) ?></textarea>
        <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
            <button class="btn btn-primary" onclick="saveComment('performance')">Save Commentary</button>
            <span id="save-feedback" style="font-size:13px;color:#34d399;display:none">Saved ✓</span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = '#2a3448';
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';

const avgPages = <?= json_encode(array_keys($avgByPage)) ?>;
const avgTimes = <?= json_encode(array_values($avgByPage)) ?>;

new Chart(document.getElementById('avgLoadChart'), {
    type: 'bar',
    data: {
        labels: avgPages.map(p => p.replace('https://test.jroner.com', '')),
        datasets: [{ label: 'Avg Load (ms)', data: avgTimes,
            backgroundColor: avgTimes.map(v => v > 5000 ? '#f87171bb' : v > 1000 ? '#fbbf24bb' : '#34d399bb'),
            borderColor:     avgTimes.map(v => v > 5000 ? '#f87171'   : v > 1000 ? '#fbbf24'   : '#34d399'),
            borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true } } }
});

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($avgDaily)) ?>,
        datasets: [{ label: 'Avg Load (ms)', data: <?= json_encode(array_values($avgDaily)) ?>,
            borderColor: '#fbbf24', backgroundColor: 'rgba(251,191,36,0.1)', fill: true, tension: 0.3,
            pointBackgroundColor: '#fbbf24', pointBorderColor: '#1a2133', pointBorderWidth: 2, pointRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true } } }
});

new Chart(document.getElementById('bucketChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($buckets)) ?>,
        datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($buckets)) ?>,
            backgroundColor: ['#34d399bb','#4f8ef7bb','#fbbf24bb','#fb923cbb','#f87171bb'],
            borderColor:     ['#34d399',  '#4f8ef7',  '#fbbf24',  '#fb923c',  '#f87171'],
            borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true } } }
});

new Chart(document.getElementById('timingChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($p) => parse_url($p, PHP_URL_PATH) ?: '/', $timingPages)) ?>,
        datasets: [
            { label: 'DNS',  data: <?= json_encode($timingDns) ?>,  backgroundColor: '#a78bfabb', stack: 'stack' },
            { label: 'TCP',  data: <?= json_encode($timingTcp) ?>,  backgroundColor: '#4f8ef7bb', stack: 'stack' },
            { label: 'TTFB', data: <?= json_encode($timingTtfb) ?>, backgroundColor: '#34d399bb', stack: 'stack' },
            { label: 'DOM',  data: <?= json_encode($timingDom) ?>,  backgroundColor: '#fbbf24bb', stack: 'stack' },
            { label: 'Load', data: <?= json_encode($timingLoad) ?>, backgroundColor: '#f87171bb', stack: 'stack' },
        ]
    },
    options: { responsive: true,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 12, font: { size: 11 } } } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, stacked: true },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true, stacked: true } } }
});

function saveComment(section) {
    const text = document.getElementById('commentBox').value;
    fetch('/api2/reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_comment', section, analyst_comments: text })
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            const fb = document.getElementById('save-feedback');
            fb.style.display = 'inline';
            setTimeout(() => fb.style.display = 'none', 2500);
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/export_modal.php'; ?>
</body>
</html>
