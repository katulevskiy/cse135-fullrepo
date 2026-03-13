<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireSection('behavioral');

$db = getDb();
$tz = date('T');

$rows = $db->query("
    SELECT session_id, page, events, event_count, saved_at
    FROM activity
    ORDER BY saved_at DESC
    LIMIT 1000
")->fetch_all(MYSQLI_ASSOC);

// Aggregate event types
$eventTypeCounts = [];
$daySeries = [];   // date -> event_count sum
$pageErrorCounts = [];  // page -> error count
$engagementBuckets = ['Active (clicks/keys/scroll)' => 0, 'Mouse Movement' => 0, 'Idle' => 0, 'Navigation' => 0, 'Errors' => 0];

foreach ($rows as $row) {
    $events = json_decode($row['events'] ?? '[]', true) ?: [];
    $day = date('Y-m-d', strtotime($row['saved_at']));
    $page = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';

    $daySeries[$day] = ($daySeries[$day] ?? 0) + (int)$row['event_count'];

    foreach ($events as $ev) {
        $type = $ev['type'] ?? 'unknown';
        $eventTypeCounts[$type] = ($eventTypeCounts[$type] ?? 0) + 1;

        // Error count by page
        if (in_array($type, ['error', 'unhandledrejection'])) {
            $pageErrorCounts[$page] = ($pageErrorCounts[$page] ?? 0) + 1;
        }

        // Engagement buckets
        if (in_array($type, ['click', 'keydown', 'keyup', 'scroll'])) {
            $engagementBuckets['Active (clicks/keys/scroll)']++;
        } elseif ($type === 'mousemove') {
            $engagementBuckets['Mouse Movement']++;
        } elseif (in_array($type, ['idle_start', 'idle_end'])) {
            $engagementBuckets['Idle']++;
        } elseif (in_array($type, ['pageview', 'page_exit', 'page'])) {
            $engagementBuckets['Navigation']++;
        } elseif (in_array($type, ['error', 'unhandledrejection'])) {
            $engagementBuckets['Errors']++;
        }
    }
}

arsort($eventTypeCounts);
ksort($daySeries);
arsort($pageErrorCounts);

$totalEvents = array_sum($eventTypeCounts);
$totalSessions = count(array_unique(array_column($rows, 'session_id')));
$errorCount = ($pageErrorCounts ? array_sum($pageErrorCounts) : 0);

// Recent activity table
$tableRows = $db->query("
    SELECT session_id, page, event_count, events, saved_at
    FROM activity
    ORDER BY saved_at DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

// Analyst comment
$user = getCurrentUser();
$comment = '';
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section='behavioral' AND created_by=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $comment = $r['analyst_comments'];

// Chart config for export modal
$chartConfig = [
    ['id' => 'eventTypeChart',   'label' => 'Event Type Distribution'],
    ['id' => 'dailyEventsChart', 'label' => 'Daily Event Volume'],
    ['id' => 'errorChart',       'label' => 'JS Errors by Page'],
    ['id' => 'engagementChart',  'label' => 'Engagement Breakdown'],
];
$section = 'behavioral';

pageHead('Behavioral Analytics');
renderNav('behavioral');
?>
<div class="content">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:16px">
        <div>
            <h1>Behavioral Analytics</h1>
            <p class="page-sub">
                User interaction analysis across <?= count($rows) ?> activity batches —
                <?= number_format($totalEvents) ?> total events, <?= $totalSessions ?> unique sessions
                <span style="color:#3d4f66;margin-left:6px">(all times <?= $tz ?>)</span>
            </p>
        </div>
        <button class="btn btn-secondary" onclick="openExportModal()">Export PDF</button>
    </div>

    <!-- KPI row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
        <?php
        $kpis = [
            ['Total Events', number_format($totalEvents), '#4f8ef7'],
            ['Unique Sessions', $totalSessions, '#34d399'],
            ['JS Errors Captured', $errorCount, $errorCount > 0 ? '#f87171' : '#34d399'],
        ];
        foreach ($kpis as [$label, $val, $color]): ?>
        <div class="card" style="text-align:center;padding:18px 24px">
            <div style="font-size:28px;font-weight:700;color:<?= $color ?>;margin-bottom:4px"><?= $val ?></div>
            <div style="font-size:12px;color:#7a8fa6"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="charts-grid" style="margin-bottom:20px">
        <div class="card">
            <div class="card-title">Event Type Distribution</div>
            <div class="card-sub">Frequency of each interaction type captured across all sessions</div>
            <canvas id="eventTypeChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Daily Event Volume</div>
            <div class="card-sub">Total number of events captured per day</div>
            <canvas id="dailyEventsChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">JS Errors by Page</div>
            <div class="card-sub">Count of captured JavaScript errors and unhandled rejections per page</div>
            <canvas id="errorChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Engagement Breakdown</div>
            <div class="card-sub">Proportion of active interaction vs. passive movement vs. idle time</div>
            <canvas id="engagementChart" height="240"></canvas>
        </div>
    </div>

    <!-- Activity Table -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-title" style="margin-bottom:16px">Recent Activity Batches (last 30)</div>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Timestamp (<?= $tz ?>)</th>
                    <th>Session ID</th>
                    <th>Page</th>
                    <th># Events</th>
                    <th>Event Types</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableRows as $row):
                    $evs = json_decode($row['events'] ?? '[]', true) ?: [];
                    $types = array_unique(array_column($evs, 'type'));
                    $path = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';
                    $hasError = in_array('error', $types) || in_array('unhandledrejection', $types);
                ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12px;color:#7a8fa6"><?= htmlspecialchars(date('M j H:i:s', strtotime($row['saved_at']))) ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#5a7090"><?= htmlspecialchars(substr($row['session_id'], 0, 12)) ?>…</td>
                    <td><?= htmlspecialchars($path) ?></td>
                    <td style="font-weight:600;color:<?= (int)$row['event_count'] > 10 ? '#fbbf24' : '#c8d6e8' ?>"><?= $row['event_count'] ?></td>
                    <td>
                        <?php foreach (array_slice($types, 0, 5) as $t): ?>
                        <span class="badge" style="background:<?= $t === 'error' ? '#2d1a1a' : '#1e2a3a' ?>;color:<?= $t === 'error' ? '#f87171' : '#60a5fa' ?>;margin-right:2px;margin-bottom:2px"><?= htmlspecialchars($t) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($types) > 5): ?><span style="color:#5a7090;font-size:11px">+<?= count($types)-5 ?> more</span><?php endif; ?>
                        <?php if ($hasError): ?><span class="badge" style="background:#2d1a1a;color:#f87171">⚠ error</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Analyst Comments -->
    <div class="card">
        <div class="card-title" style="margin-bottom:4px">Analyst Commentary</div>
        <div class="card-sub">Interpret the behavioral patterns — engagement quality, error trends, interaction signatures.</div>
        <div style="margin-bottom:10px"></div>
        <textarea id="commentBox" placeholder="e.g. Mouse movement dominates event volume (438 events), indicating passive browsing rather than active engagement. The 2 JS errors are concentrated on product-detail.html — consistent with chaos.js interference on that page. Click-to-scroll ratio suggests users scan pages rather than interact deeply. Idle events suggest visitors leave tabs open..."><?= htmlspecialchars($comment) ?></textarea>
        <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
            <button class="btn btn-primary" onclick="saveComment('behavioral')">Save Commentary</button>
            <span id="save-feedback" style="font-size:13px;color:#34d399;display:none">Saved ✓</span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = '#2a3448';
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';

const palette = ['#4f8ef7','#34d399','#fbbf24','#f87171','#a78bfa','#fb923c','#38bdf8','#e879f9','#6ee7b7'];

new Chart(document.getElementById('eventTypeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($eventTypeCounts)) ?>,
        datasets: [{ label: 'Events', data: <?= json_encode(array_values($eventTypeCounts)) ?>,
            backgroundColor: <?= json_encode(array_keys($eventTypeCounts)) ?>.map((t, i) =>
                t === 'error' || t === 'unhandledrejection' ? '#f87171bb' : palette[i % palette.length] + 'bb'),
            borderColor: <?= json_encode(array_keys($eventTypeCounts)) ?>.map((t, i) =>
                t === 'error' || t === 'unhandledrejection' ? '#f87171' : palette[i % palette.length]),
            borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true } } }
});

new Chart(document.getElementById('dailyEventsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($daySeries)) ?>,
        datasets: [{ label: 'Events', data: <?= json_encode(array_values($daySeries)) ?>,
            borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.12)', fill: true, tension: 0.3,
            pointBackgroundColor: '#a78bfa', pointBorderColor: '#1a2133', pointBorderWidth: 2, pointRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true } } }
});

const errPages = <?= json_encode(array_keys($pageErrorCounts)) ?>;
const errCounts = <?= json_encode(array_values($pageErrorCounts)) ?>;
new Chart(document.getElementById('errorChart'), {
    type: 'bar',
    data: {
        labels: errPages.length ? errPages.map(p => p.replace('https://test.jroner.com','')) : ['No errors detected'],
        datasets: [{ label: 'Errors', data: errCounts.length ? errCounts : [0],
            backgroundColor: '#f87171bb', borderColor: '#f87171', borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true } } }
});

new Chart(document.getElementById('engagementChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($engagementBuckets)) ?>,
        datasets: [{ data: <?= json_encode(array_values($engagementBuckets)) ?>,
            backgroundColor: ['#4f8ef7','#a78bfa','#fbbf24','#34d399','#f87171'],
            borderColor: '#1a2133', borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 12, font: { size: 11 } } } } }
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
