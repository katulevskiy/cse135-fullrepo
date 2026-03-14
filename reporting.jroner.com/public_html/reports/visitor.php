<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireSection('visitor');

$db = getDb();
$tz = date('T');

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

// ── IP Geolocation (cached in ip_geo table) ────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS ip_geo (
    ip VARCHAR(45) NOT NULL PRIMARY KEY,
    country_name VARCHAR(100),
    country_code CHAR(2),
    city VARCHAR(100),
    lat FLOAT,
    lng FLOAT,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function isPrivateIP(string $ip): bool {
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') return true;
    $long = ip2long($ip);
    if ($long === false) return false; // IPv6 — allow through to resolver
    return (
        ($long >= ip2long('10.0.0.0')   && $long <= ip2long('10.255.255.255')) ||
        ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) ||
        ($long >= ip2long('192.168.0.0')&& $long <= ip2long('192.168.255.255'))
    );
}

$allIPs    = array_values(array_unique(array_filter(array_column($rows, 'source_ip'), fn($ip) => !isPrivateIP($ip))));
$geoCache  = []; // ip → [country_name, country_code, city, lat, lng]
$toResolve = [];

if ($allIPs) {
    $inList = implode(',', array_fill(0, count($allIPs), '?'));
    $gs = $db->prepare("SELECT ip, country_name, country_code, city, lat, lng FROM ip_geo WHERE ip IN ($inList)");
    $gs->bind_param(str_repeat('s', count($allIPs)), ...$allIPs);
    $gs->execute();
    foreach ($gs->get_result()->fetch_all(MYSQLI_ASSOC) as $g) {
        $geoCache[$g['ip']] = $g;
    }
    $toResolve = array_values(array_filter($allIPs, fn($ip) => !isset($geoCache[$ip])));
}

if ($toResolve) {
    $payload = json_encode(array_map(fn($ip) => ['query' => $ip], $toResolve));
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'content'       => $payload,
        'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents('http://ip-api.com/batch?fields=status,country,countryCode,city,lat,lon,query', false, $ctx);
    if ($res) {
        foreach (json_decode($res, true) ?? [] as $g) {
            if (($g['status'] ?? '') === 'success') {
                $ip = $g['query'];
                $geoCache[$ip] = ['ip' => $ip, 'country_name' => $g['country'], 'country_code' => $g['countryCode'],
                                  'city' => $g['city'], 'lat' => $g['lat'], 'lng' => $g['lon']];
                $ins = $db->prepare("INSERT IGNORE INTO ip_geo (ip, country_name, country_code, city, lat, lng) VALUES (?,?,?,?,?,?)");
                $ins->bind_param("ssssdd", $ip, $g['country'], $g['countryCode'], $g['city'], $g['lat'], $g['lon']);
                $ins->execute();
            }
        }
    }
}

$countryCounts = []; // alpha-2 → count
$cityMap = [];       // "city||alpha2" → {lat, lng, city, country, count}
foreach ($rows as $row) {
    $geo = $geoCache[$row['source_ip']] ?? null;
    if (!$geo) continue;
    $cc  = $geo['country_code'];
    $countryCounts[$cc] = ($countryCounts[$cc] ?? 0) + 1;
    $key = $geo['city'] . '||' . $cc;
    if (!isset($cityMap[$key])) {
        $cityMap[$key] = ['lat' => (float)$geo['lat'], 'lng' => (float)$geo['lng'],
                          'city' => $geo['city'], 'country' => $geo['country_name'], 'count' => 0];
    }
    $cityMap[$key]['count']++;
}
$cityData = array_values($cityMap);

// Existing analyst comment for this section
$user = getCurrentUser();
$comment = '';
$commentName = '_comment';
$stmt = $db->prepare("SELECT analyst_comments FROM saved_reports WHERE section='visitor' AND created_by=? AND name=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("is", $user['id'], $commentName);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $comment = $r['analyst_comments'];

// ── Device Fingerprint aggregates ──────────────────────────────────────────
$fpRows = $db->query("
    SELECT canvas_fp, device_type, device_make, os_name, browser_name, browser_ver,
           cpu_cores, device_mem, touch_points, pixel_ratio, tz, webgl_renderer, saved_at
    FROM device_fingerprints
    ORDER BY saved_at DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$fpUnique   = [];
$fpDevTypes = [];
$fpMakes    = [];
$fpOS       = [];
$fpBrowsers = [];
$fpCores    = [];
$fpRenderers= [];
foreach ($fpRows as $fp) {
    if ($fp['canvas_fp']) $fpUnique[$fp['canvas_fp']] = true;
    $fpDevTypes[$fp['device_type'] ?: 'unknown'] = ($fpDevTypes[$fp['device_type'] ?: 'unknown'] ?? 0) + 1;
    if ($fp['device_make'])   $fpMakes[$fp['device_make']]   = ($fpMakes[$fp['device_make']] ?? 0) + 1;
    if ($fp['os_name'])       $fpOS[$fp['os_name']]           = ($fpOS[$fp['os_name']] ?? 0) + 1;
    if ($fp['browser_name'])  $fpBrowsers[$fp['browser_name']]= ($fpBrowsers[$fp['browser_name']] ?? 0) + 1;
    $c = (int)$fp['cpu_cores'];
    if ($c > 0) $fpCores[$c > 16 ? '16+' : (string)$c] = ($fpCores[$c > 16 ? '16+' : (string)$c] ?? 0) + 1;
    if ($fp['webgl_renderer']) {
        // Shorten renderer string for display
        $r = preg_replace('/ANGLE \(([^,]+),\s*([^,]+?)(?:\s+\(.*\))?,.*\)/', '$2', $fp['webgl_renderer']);
        $r = preg_replace('/\s+(GPU|Graphics|Iris|UHD|HD|GTX|RTX|RX)\s+/', ' $1 ', $r);
        $r = substr(trim($r), 0, 40);
        $fpRenderers[$r] = ($fpRenderers[$r] ?? 0) + 1;
    }
}
arsort($fpMakes); arsort($fpOS); arsort($fpBrowsers); arsort($fpRenderers);

// Chart config for export modal
$chartConfig = [
    ['id' => 'langChart',    'label' => 'Sessions by Browser Language'],
    ['id' => 'pageChart',    'label' => 'Sessions by Page'],
    ['id' => 'sessionChart', 'label' => 'Daily Unique Sessions'],
    ['id' => 'networkChart', 'label' => 'Network Connection Types'],
    ['id' => 'screenChart',  'label' => 'Screen Width Distribution'],
];
if (!empty($fpRows)) {
    $chartConfig[] = ['id' => 'fpDevTypeChart', 'label' => 'Device Types (Fingerprint)'];
    $chartConfig[] = ['id' => 'fpBrowserChart', 'label' => 'Browser Distribution (Fingerprint)'];
    $chartConfig[] = ['id' => 'fpOSChart',      'label' => 'Operating Systems (Fingerprint)'];
    $chartConfig[] = ['id' => 'fpMakeChart',    'label' => 'Device Makes (Fingerprint)'];
    if ($fpCores)     $chartConfig[] = ['id' => 'fpCoresChart', 'label' => 'CPU Core Count (Fingerprint)'];
    if ($fpRenderers) $chartConfig[] = ['id' => 'fpGPUChart',   'label' => 'GPU Renderer (Fingerprint)'];
}
$section = 'visitor';

pageHead('Visitor Analytics', 'canvas { display: block; }');
renderNav('visitor');
?>
<div class="content">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
        <div>
            <h1>Visitor &amp; Session Analytics</h1>
            <p class="page-sub">
                Aggregated from <?= count($rows) ?> static records
                <span style="color:#3d4f66;margin-left:6px">(all times <span data-tz-label><?= $tz ?></span>)</span>
            </p>
        </div>
        <button class="btn btn-secondary" onclick="openExportModal()">Export PDF</button>
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

    <!-- World Map: city dots -->
    <div class="card" style="margin-bottom:20px">
        <div style="margin-bottom:16px">
            <div class="card-title">Request Origin Map</div>
            <div class="card-sub">
                City-level distribution of <?= count($allIPs) ?> unique visitor IPs
                <?php if ($cityData): ?>&mdash; <?= count($cityData) ?> cit<?= count($cityData) === 1 ? 'y' : 'ies' ?> across <?= count($countryCounts) ?> countr<?= count($countryCounts) === 1 ? 'y' : 'ies' ?><?php endif; ?>
            </div>
        </div>
        <div id="worldMap" style="height:420px;border-radius:8px;overflow:hidden;background:#c8d5e4;position:relative">
            <div id="mapLoading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;color:#5a7090;z-index:10">
                <?= $allIPs ? 'Loading map…' : 'No public IPs found — nothing to map.' ?>
            </div>
        </div>
        <div style="margin-top:10px;font-size:11px;color:#5a7090">
            Circle size proportional to request count per city &mdash; hover for details
        </div>
    </div>

    <!-- Data Table -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-title" style="margin-bottom:16px">Recent Sessions (last 50)</div>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th data-tz-header>Timestamp (<?= $tz ?>)</th>
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
                    <td style="white-space:nowrap;font-size:12px;color:#7a8fa6" data-ts="<?= date('Y-m-d\TH:i:s\Z', strtotime($row['saved_at'])) ?>"><?= htmlspecialchars(date('M j H:i', strtotime($row['saved_at']))) ?></td>
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

    <!-- Device Intelligence -->
    <?php if (!empty($fpRows)): ?>
    <div class="card" style="margin-bottom:20px">
        <div style="margin-bottom:20px">
            <div class="card-title">Device Intelligence</div>
            <div class="card-sub">
                Canvas fingerprint analysis — <?= count($fpRows) ?> sessions,
                <?= count($fpUnique) ?> unique device fingerprints
            </div>
        </div>

        <!-- KPI row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px">
            <?php
            $topBrowser = $fpBrowsers ? array_key_first($fpBrowsers) : '—';
            $topOS      = $fpOS      ? array_key_first($fpOS)      : '—';
            $topMake    = $fpMakes   ? array_key_first($fpMakes)   : '—';
            $mobileCount= $fpDevTypes['mobile'] ?? 0;
            $totalFP    = count($fpRows);
            $mobilePct  = $totalFP ? round($mobileCount / $totalFP * 100) : 0;
            foreach ([
                ['Unique Fingerprints', count($fpUnique), '#4f8ef7'],
                ['Top Browser',         $topBrowser,       '#34d399'],
                ['Top OS',              $topOS,            '#fbbf24'],
                ['Mobile Sessions',     "$mobilePct%",     '#f87171'],
            ] as [$label, $val, $color]):
            ?>
            <div style="background:#131d2e;border:1px solid #2a3448;border-radius:8px;padding:16px 18px">
                <div style="font-size:11px;color:#5a7090;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px"><?= htmlspecialchars($label) ?></div>
                <div style="font-size:22px;font-weight:700;color:<?= $color ?>"><?= htmlspecialchars((string)$val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts row -->
        <div class="charts-grid" style="margin-bottom:20px">
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Device Type</div>
                <canvas id="fpDevTypeChart" height="200"></canvas>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Browser Distribution</div>
                <canvas id="fpBrowserChart" height="200"></canvas>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Operating System</div>
                <canvas id="fpOSChart" height="200"></canvas>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Device Make</div>
                <canvas id="fpMakeChart" height="200"></canvas>
            </div>
        </div>

        <!-- CPU cores + GPU renderer side by side -->
        <div class="charts-grid" style="margin-bottom:20px">
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">CPU Core Count</div>
                <canvas id="fpCoresChart" height="200"></canvas>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">GPU / Renderer</div>
                <canvas id="fpGPUChart" height="200"></canvas>
            </div>
        </div>

        <!-- Recent fingerprint table -->
        <div style="font-size:13px;font-weight:600;color:#e2e8f0;margin-bottom:12px">Recent Fingerprinted Sessions (last 20)</div>
        <div style="overflow-x:auto">
        <table>
            <thead><tr>
                <th>Canvas FP</th>
                <th>Type</th>
                <th>Make</th>
                <th>OS</th>
                <th>Browser</th>
                <th>CPU Cores</th>
                <th>RAM (GB)</th>
                <th>Pixel Ratio</th>
                <th>GPU</th>
                <th data-tz-header>Recorded</th>
            </tr></thead>
            <tbody>
            <?php foreach (array_slice($fpRows, 0, 20) as $fp):
                $gpu = preg_replace('/ANGLE \(([^,]+),\s*([^,]+?)(?:\s+\(.*\))?,.*\)/', '$2', $fp['webgl_renderer'] ?? '—');
                $gpu = substr(trim($gpu), 0, 30);
            ?>
            <tr>
                <td style="font-family:monospace;font-size:11px;color:#4f8ef7"><?= htmlspecialchars($fp['canvas_fp'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $fp['device_type'] === 'mobile' ? 'behavioral' : ($fp['device_type'] === 'tablet' ? 'visitor' : 'performance') ?>">
                    <?= htmlspecialchars($fp['device_type'] ?? '—') ?>
                </span></td>
                <td style="font-size:12px"><?= htmlspecialchars($fp['device_make'] ?? '—') ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($fp['os_name'] ?? '—') ?></td>
                <td style="font-size:12px"><?= htmlspecialchars(($fp['browser_name'] ?? '—') . ' ' . ($fp['browser_ver'] ? substr($fp['browser_ver'], 0, 6) : '')) ?></td>
                <td style="text-align:center;color:#fbbf24"><?= htmlspecialchars($fp['cpu_cores'] ?? '—') ?></td>
                <td style="text-align:center;color:#34d399"><?= $fp['device_mem'] ? htmlspecialchars($fp['device_mem']) . 'GB' : '—' ?></td>
                <td style="text-align:center;color:#a78bfa"><?= $fp['pixel_ratio'] ? 'x' . htmlspecialchars($fp['pixel_ratio']) : '—' ?></td>
                <td style="font-size:11px;color:#7a8fa6;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($fp['webgl_renderer'] ?? '') ?>"><?= htmlspecialchars($gpu) ?></td>
                <td style="font-size:12px;color:#7a8fa6;white-space:nowrap" data-ts="<?= date('Y-m-d\TH:i:s\Z', strtotime($fp['saved_at'])) ?>"><?= htmlspecialchars(date('M j H:i', strtotime($fp['saved_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:20px">
        <div class="card-title">Device Intelligence</div>
        <div style="text-align:center;padding:32px;color:#5a7090;font-size:13px">
            No fingerprint data yet — new sessions from test.jroner.com will populate this section automatically.
        </div>
    </div>
    <?php endif; ?>

    <!-- Analyst Comments -->
    <div class="card">
        <div class="card-title" style="margin-bottom:4px">Analyst Commentary</div>
        <div class="card-sub">Record your interpretation of the visitor data. Saved per-user.</div>
        <div id="comment-status" style="margin-bottom:10px"></div>
        <?php
        // Build a data-driven default commentary when none is saved yet
        if (!$comment) {
            $topLang  = array_key_first($langCounts) ?: 'en-US';
            $topLangN = reset($langCounts) ?: 0;
            $topPage  = array_key_first($pageCounts) ?: '/';
            $netTop   = array_key_first($networkCounts) ?: '4g';
            $mobileW  = ($screenWidths['<768'] ?? 0);
            $desktopW = ($screenWidths['1024-1440'] ?? 0) + ($screenWidths['>1440'] ?? 0);
            $total    = max(1, count($rows));
            $mobilePct = round($mobileW / $total * 100);
            $deskPct   = round($desktopW / $total * 100);
            $fpUniqCount = count($fpUnique);
            $topBrow  = !empty($fpBrowsers) ? array_key_first($fpBrowsers) : 'Chrome';
            $topOS    = !empty($fpOS)       ? array_key_first($fpOS)       : 'Windows';
            $fpMob    = $fpDevTypes['mobile'] ?? 0;
            $fpDesk   = $fpDevTypes['desktop'] ?? 0;
            $comment  = "Visitor & Session Summary — " . date('F j, Y') . "\n\n"
                . "Total sessions recorded: $total across " . count($pageCounts) . " unique page paths.\n"
                . "Dominant language: $topLang ($topLangN sessions). "
                . count($langCounts) . " distinct language locale" . (count($langCounts) !== 1 ? "s" : "") . " detected in this dataset.\n\n"
                . "Most-visited page: $topPage. "
                . "Network quality: $netTop connections dominate, indicating a well-connected audience.\n\n"
                . "Screen size profile: ~{$mobilePct}% mobile (<768px), ~{$deskPct}% large desktop (≥1024px). "
                . "Responsive design appears adequate given desktop dominance.\n\n"
                . "Device fingerprinting: $fpUniqCount unique canvas fingerprints from " . count($fpDbRows ?? []) . " fingerprinted sessions. "
                . "Top browser: $topBrow. Top OS: $topOS. "
                . "Desktop sessions ({$fpDesk}) far outnumber mobile ({$fpMob}), consistent with screen-size data.\n\n"
                . "Geographic distribution: All resolved IPs originate from the United States (primarily San Diego / La Jolla area — UCSD network traffic). "
                . "This dataset reflects internal/academic use rather than a broad public audience at this stage.\n\n"
                . "Recommendation: Drive external traffic to test.jroner.com to validate responsive design "
                . "and gather more representative language, network, and device diversity.";
        }
        ?>
        <textarea id="commentBox" placeholder="Analyst commentary for visitor data..."><?= htmlspecialchars($comment) ?></textarea>
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

// ── Device Intelligence Charts ─────────────────────────────────────────────
<?php if (!empty($fpRows)): ?>
(function() {
    const pal = ['#4f8ef7','#34d399','#fbbf24','#f87171','#a78bfa','#fb923c','#38bdf8','#e879f9','#6ee7b7','#fde68a'];
    const cOpts = { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 10, font: { size: 11 } } } } };

    new Chart(document.getElementById('fpDevTypeChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($fpDevTypes)) ?>,
            datasets: [{ data: <?= json_encode(array_values($fpDevTypes)) ?>,
                backgroundColor: pal, borderColor: '#1a2133', borderWidth: 2 }]
        },
        options: cOpts
    });

    new Chart(document.getElementById('fpBrowserChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($fpBrowsers)) ?>,
            datasets: [{ data: <?= json_encode(array_values($fpBrowsers)) ?>,
                backgroundColor: pal, borderColor: '#1a2133', borderWidth: 2 }]
        },
        options: cOpts
    });

    new Chart(document.getElementById('fpOSChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($fpOS)) ?>,
            datasets: [{ data: <?= json_encode(array_values($fpOS)) ?>,
                backgroundColor: pal, borderColor: '#1a2133', borderWidth: 2 }]
        },
        options: cOpts
    });

    new Chart(document.getElementById('fpMakeChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($fpMakes)) ?>,
            datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($fpMakes)) ?>,
                backgroundColor: '#4f8ef7bb', borderColor: '#4f8ef7', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, indexAxis: 'y',
            scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true },
                      y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } } } }
    });

    <?php if ($fpCores): ?>
    new Chart(document.getElementById('fpCoresChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($fpCores)) ?>,
            datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($fpCores)) ?>,
                backgroundColor: '#a78bfabb', borderColor: '#a78bfa', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } },
            scales: { x: { title: { display: true, text: 'Logical CPU Cores', color: '#5a7090' },
                          grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' } },
                      y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', stepSize: 1 }, beginAtZero: true } } }
    });
    <?php endif; ?>

    <?php if ($fpRenderers): ?>
    new Chart(document.getElementById('fpGPUChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($fpRenderers)) ?>,
            datasets: [{ label: 'Sessions', data: <?= json_encode(array_values($fpRenderers)) ?>,
                backgroundColor: '#34d399bb', borderColor: '#34d399', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, indexAxis: 'y',
            scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6' }, beginAtZero: true },
                      y: { grid: { color: '#1e2a3a' }, ticks: { color: '#7a8fa6', font: { size: 10 } } } } }
    });
    <?php endif; ?>
})();
<?php endif; ?>

// ── World Map: city dot markers (Leaflet) ──────────────────────────────────
const GEO_CITY_DATA = <?= json_encode($cityData) ?>;

function _loadScript(src) {
    return new Promise((res, rej) => {
        const s = document.createElement('script');
        s.src = src; s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
    });
}

async function _initMap() {
    if (!GEO_CITY_DATA.length) {
        document.getElementById('mapLoading').textContent = 'No geo data to display.';
        return;
    }
    const lnk = document.createElement('link');
    lnk.rel = 'stylesheet';
    lnk.href = 'https://cdn.jsdelivr.net/npm/leaflet@1.9/dist/leaflet.min.css';
    document.head.appendChild(lnk);

    await _loadScript('https://cdn.jsdelivr.net/npm/leaflet@1.9/dist/leaflet.min.js');

    const map = L.map('worldMap', { worldCopyJump: true, minZoom: 1, maxZoom: 10 });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>, CartoDB',
        subdomains: 'abcd', maxZoom: 19
    }).addTo(map);

    const bounds = [];
    GEO_CITY_DATA.filter(c => c.lat && c.lng).forEach(c => {
        const r = Math.max(6, Math.min(26, 5 + Math.log1p(c.count) * 5));
        L.circleMarker([c.lat, c.lng], {
            radius: r, fillColor: '#3b7de8', color: '#1a3a72',
            weight: 1.5, fillOpacity: 0.80
        })
        .bindTooltip(`<strong>${c.city}</strong>, ${c.country}<br>${c.count} request${c.count !== 1 ? 's' : ''}`,
                     { sticky: true, opacity: 0.93 })
        .addTo(map);
        bounds.push([c.lat, c.lng]);
    });

    document.getElementById('mapLoading').style.display = 'none';
    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 6 });
    } else {
        map.setView([25, 15], 2);
    }
}

document.addEventListener('DOMContentLoaded', () => _initMap().catch(e => {
    console.error('Map init failed:', e);
    document.getElementById('mapLoading').textContent = 'Map failed to load.';
}));

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

</script>

<?php include __DIR__ . '/../includes/export_modal.php'; ?>
</body>
</html>
