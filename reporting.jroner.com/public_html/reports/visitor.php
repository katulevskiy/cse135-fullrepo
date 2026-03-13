<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireSection('visitor');

$db = getDb();

// --- Data queries ---
$rows = $db->query("
    SELECT session_id, page, language, screen, window_size, network, source_ip, saved_at
    FROM `static`
    ORDER BY saved_at DESC
    LIMIT 500
")->fetch_all(MYSQLI_ASSOC);

// Aggregates
$langCounts = [];
$pageCounts = [];
$networkCounts = [];
$screenWidths = ['<768' => 0, '768-1024' => 0, '1024-1440' => 0, '>1440' => 0];
$daySessions = [];

foreach ($rows as $row) {
    // Language
    $lang = $row['language'] ?: 'unknown';
    $langCounts[$lang] = ($langCounts[$lang] ?? 0) + 1;

    // Page path
    $path = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';
    $pageCounts[$path] = ($pageCounts[$path] ?? 0) + 1;

    // Network
    $net = json_decode($row['network'] ?? '{}', true);
    $eff = $net['effectiveType'] ?? 'unknown';
    $networkCounts[$eff] = ($networkCounts[$eff] ?? 0) + 1;

    // Screen width bucket
    $scr = json_decode($row['screen'] ?? '{}', true);
    $w = (int)($scr['width'] ?? 0);
    if ($w < 768)        $screenWidths['<768']++;
    elseif ($w < 1024)   $screenWidths['768-1024']++;
    elseif ($w < 1440)   $screenWidths['1024-1440']++;
    else                 $screenWidths['>1440']++;

    // Daily unique sessions
    $sid = $row['session_id'];
    $day = date('Y-m-d', strtotime($row['saved_at']));
    if ($sid) $daySessions[$day][$sid] = true;
}
arsort($langCounts);
arsort($pageCounts);
$dayCounts = array_map('count', $daySessions);
ksort($dayCounts);

// Table: last 50 rows
$tableRows = $db->query("
    SELECT session_id, page, language, screen, network, source_ip, saved_at
    FROM `static`
    ORDER BY saved_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Existing analyst comment for this section
$user = getCurrentUser();
$comment = '';
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section='visitor' AND created_by=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $comment = $r['analyst_comments'];

pageHead('Visitor Analytics', 'canvas { display: block; }');
renderNav('visitor');
?>
<div class="content">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
        <div>
            <h1>Visitor &amp; Session Analytics</h1>
            <p class="page-sub">Aggregated from <?= count($rows) ?> static records across all sessions</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-secondary" id="exportBtn" onclick="exportPDF('visitor')">Export PDF</button>
        </div>
    </div>

    <div class="charts-grid" style="margin-bottom:20px">
        <div class="card">
            <div class="card-title">Sessions by Browser Language</div>
            <div class="card-sub">Number of sessions per reported browser language setting</div>
            <canvas id="langChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Sessions by Page</div>
            <div class="card-sub">Distribution of recorded sessions across page paths</div>
            <canvas id="pageChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Daily Unique Sessions</div>
            <div class="card-sub">Number of distinct sessions recorded per day</div>
            <canvas id="sessionChart" height="240"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Network Connection Types</div>
            <div class="card-sub">Breakdown of visitor network quality (4G, 3G, etc.)</div>
            <canvas id="networkChart" height="240"></canvas>
        </div>
    </div>

    <!-- Screen width bar chart full-width -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-title">Screen Width Distribution</div>
        <div class="card-sub">Categorized screen widths of visitors — useful for responsive design decisions</div>
        <canvas id="screenChart" height="100"></canvas>
    </div>

    <!-- Data Table -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-title" style="margin-bottom:16px">Recent Sessions (last 50)</div>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Session ID</th>
                    <th>Page</th>
                    <th>Language</th>
                    <th>Screen</th>
                    <th>Network</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableRows as $row):
                    $scr = json_decode($row['screen'] ?? '{}', true);
                    $net = json_decode($row['network'] ?? '{}', true);
                    $path = parse_url($row['page'] ?? '', PHP_URL_PATH) ?: '/';
                ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12px;color:#7a8fa6"><?= htmlspecialchars(date('M j H:i', strtotime($row['saved_at']))) ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#5a7090"><?= htmlspecialchars(substr($row['session_id'], 0, 12)) ?>…</td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($row['page'] ?? '') ?>"><?= htmlspecialchars($path) ?></td>
                    <td><?= htmlspecialchars($row['language'] ?: '—') ?></td>
                    <td style="white-space:nowrap"><?= ($scr['width'] ?? '?') . '×' . ($scr['height'] ?? '?') ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($net['effectiveType'] ?? 'unknown') === '4g' ? 'performance' : 'behavioral' ?>"><?= htmlspecialchars($net['effectiveType'] ?? '—') ?></span></td>
                    <td style="font-size:12px;color:#5a7090"><?= htmlspecialchars($row['source_ip']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Analyst Comments -->
    <div class="card">
        <div class="card-title" style="margin-bottom:4px">Analyst Commentary</div>
        <div class="card-sub">Record your interpretation of the visitor data. Saved per-user.</div>
        <div id="comment-status" style="margin-bottom:10px"></div>
        <textarea id="commentBox" placeholder="e.g. The majority of visitors use en-US locale on 4G connections, browsing product detail pages from 1440px+ screens. The low number of mobile (<768px) visitors suggests our primary audience is desktop users..."><?= htmlspecialchars($comment) ?></textarea>
        <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
            <button class="btn btn-primary" onclick="saveComment('visitor')">Save Commentary</button>
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

new Chart(document.getElementById('langChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($langCounts)) ?>,
        datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($langCounts)) ?>,
            backgroundColor: '#4f8ef7bb', borderColor: '#4f8ef7', borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true } } }
});

new Chart(document.getElementById('pageChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_keys($pageCounts)) ?>,
        datasets: [{ data: <?= json_encode(array_values($pageCounts)) ?>,
            backgroundColor: palette, borderColor: '#1a2133', borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 12, font: { size: 11 } } } } }
});

new Chart(document.getElementById('sessionChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($dayCounts)) ?>,
        datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($dayCounts)) ?>,
            borderColor: '#4f8ef7', backgroundColor: 'rgba(79,142,247,0.12)', fill: true, tension: 0.3,
            pointBackgroundColor: '#4f8ef7', pointBorderColor: '#1a2133', pointBorderWidth: 2, pointRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true } } }
});

new Chart(document.getElementById('networkChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($networkCounts)) ?>,
        datasets: [{ data: <?= json_encode(array_values($networkCounts)) ?>,
            backgroundColor: ['#34d399','#fbbf24','#f87171','#a78bfa'], borderColor: '#1a2133', borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 12, font: { size: 11 } } } } }
});

new Chart(document.getElementById('screenChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($screenWidths)) ?>,
        datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($screenWidths)) ?>,
            backgroundColor: ['#a78bfa','#4f8ef7','#34d399','#fbbf24'], borderRadius: 4 }]
    },
    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true },
                  y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } } } }
});

function saveComment(section) {
    const text = document.getElementById('commentBox').value;
    fetch('/api2/reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_comment', section, analyst_comments: text })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const fb = document.getElementById('save-feedback');
            fb.style.display = 'inline';
            setTimeout(() => fb.style.display = 'none', 2500);
        }
    })
    .catch(e => console.error(e));
}

function exportPDF(section) {
    const btn = document.getElementById('exportBtn');
    btn.textContent = 'Generating…';
    btn.disabled = true;
    fetch('/api2/export.php?section=' + section)
        .then(r => r.json())
        .then(data => {
            btn.textContent = 'Export PDF';
            btn.disabled = false;
            if (data.url) {
                window.open(data.url, '_blank');
            } else {
                alert('Export failed: ' + (data.error || 'unknown error'));
            }
        })
        .catch(e => {
            btn.textContent = 'Export PDF';
            btn.disabled = false;
            alert('Export request failed.');
        });
}
</script>
</body>
</html>
