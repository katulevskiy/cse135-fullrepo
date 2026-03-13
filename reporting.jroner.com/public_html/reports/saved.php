<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db   = getDb();
$user = getCurrentUser();

// Run migration once (idempotent) — ADD COLUMN IF NOT EXISTS not supported on this MySQL version
$colCheck = $db->query("SHOW COLUMNS FROM saved_reports LIKE 'config_json'");
if ($colCheck && $colCheck->num_rows === 0) {
    $db->query("ALTER TABLE saved_reports ADD COLUMN config_json TEXT DEFAULT NULL");
}

// Fetch reports (config_json included)
$reports = $db->query("
    SELECT r.id, r.name, r.section, r.analyst_comments, r.config_json,
           r.html_path, r.pdf_path, r.created_at, r.created_by,
           u.username as creator
    FROM saved_reports r
    JOIN users u ON u.id = r.created_by
    WHERE r.name != '_comment'
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$canSave = in_array($user['role'], ['super_admin', 'analyst']);

// Which sections can this user see?
$availSections = [];
if (canSeeSection('visitor'))     $availSections[] = 'visitor';
if (canSeeSection('performance')) $availSections[] = 'performance';
if (canSeeSection('behavioral'))  $availSections[] = 'behavioral';

pageHead('Saved Reports', '
    .report-card{background:#1a2133;border:1px solid #2a3448;border-radius:10px;padding:20px 24px;margin-bottom:14px}
    .report-card:hover{border-color:#3a4a60}
    .report-name{font-size:16px;font-weight:600;color:#f0f4ff;margin-bottom:6px}
    .report-meta{font-size:12px;color:#5a7090;display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .report-comment{font-size:13px;color:#94a3b8;line-height:1.6;padding:12px;background:#131d2e;border-radius:6px;border-left:3px solid #4f8ef7;white-space:pre-wrap;max-height:120px;overflow:hidden}

    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:#1a2133;border:1px solid #2a3448;border-radius:10px;padding:28px 28px 20px;width:100%;max-width:660px;max-height:90vh;display:flex;flex-direction:column}
    .modal-body{overflow-y:auto;flex:1;padding-right:6px}
    .modal h2{font-size:18px;font-weight:600;color:#f0f4ff;margin-bottom:16px}
    .form-group{margin-bottom:14px}
    .form-label{display:block;font-size:11px;font-weight:700;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
    input[type=text],select,textarea{width:100%;background:#0f1623;border:1px solid #2a3448;border-radius:6px;padding:9px 12px;font-size:13px;color:#e2e8f0;outline:none;box-sizing:border-box}
    input[type=text]:focus,select:focus,textarea:focus{border-color:#4f8ef7}
    textarea{resize:vertical}

    /* Section toggle buttons */
    .sec-toggle{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#0f1623;border:1px solid #2a3448;border-radius:8px;cursor:pointer;transition:border-color .15s;user-select:none}
    .sec-toggle:hover{border-color:#3a4a60}
    .sec-toggle.active{border-color:#4f8ef7;background:#111d30}
    .sec-toggle input[type=checkbox]{width:15px;height:15px;accent-color:#4f8ef7;cursor:pointer;flex-shrink:0}
    .sec-toggle-label{font-size:13px;font-weight:600;color:#c8d5e8}

    /* Section config panel */
    .sec-panel{background:#131d2e;border:1px solid #253040;border-radius:8px;padding:16px;margin-top:8px;display:none}
    .sec-panel.open{display:block}
    .chart-grid{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 12px}
    .chart-cb-label{display:flex;align-items:center;gap:6px;font-size:12px;color:#94a3b8;padding:4px 8px;background:#0f1623;border:1px solid #2a3448;border-radius:4px;cursor:pointer;white-space:nowrap}
    .chart-cb-label input{accent-color:#4f8ef7}
    .chart-cb-label:hover{border-color:#3a4a60;color:#c8d5e8}
    .toggle-all-link{font-size:11px;color:#4f8ef7;cursor:pointer;margin-left:auto;text-decoration:none}
    .toggle-all-link:hover{text-decoration:underline}
    .include-data-row{display:flex;align-items:center;gap:8px;font-size:12px;color:#94a3b8;margin-bottom:12px}
    .include-data-row input{accent-color:#4f8ef7;width:14px;height:14px}

    /* Progress bar */
    .progress-bar-wrap{height:4px;background:#1a2133;border-radius:2px;margin-top:6px;overflow:hidden;display:none}
    .progress-bar{height:100%;background:#4f8ef7;border-radius:2px;transition:width .3s ease;width:0}
');
renderNav('saved');
?>
<div class="content">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
        <div>
            <h1>Saved Reports</h1>
            <p class="page-sub">
                <?php if ($user['role'] === 'viewer'): ?>
                    View-only access to analyst-saved reports
                <?php else: ?>
                    Named reports accessible to all users including viewers
                <?php endif; ?>
            </p>
        </div>
        <?php if ($canSave && $availSections): ?>
        <button class="btn btn-primary" onclick="openSaveModal()">+ Save New Report</button>
        <?php endif; ?>
    </div>

    <div id="save-status"></div>

    <?php if (empty($reports)): ?>
    <div class="card" style="text-align:center;padding:48px 24px">
        <div style="font-size:40px;margin-bottom:12px">📋</div>
        <div style="font-size:16px;font-weight:600;color:#f0f4ff;margin-bottom:8px">No saved reports yet</div>
        <div style="font-size:13px;color:#5a7090">
            <?= $canSave ? 'Use the "Save New Report" button to create a report accessible to all users.' : 'No reports have been published by analysts yet.' ?>
        </div>
    </div>
    <?php else: ?>

    <?php foreach ($reports as $r):
        // Determine which sections this report covers
        $rSections = [$r['section']];
        if ($r['config_json']) {
            $cfg = json_decode($r['config_json'], true);
            if (!empty($cfg['sections'])) {
                $rSections = array_column($cfg['sections'], 'key');
            }
        }
        // Preview comment: first section's comment from config_json, fallback to analyst_comments
        $previewComment = $r['analyst_comments'] ?? '';
        if ($r['config_json']) {
            $cfg = json_decode($r['config_json'], true);
            $firstComment = $cfg['sections'][0]['comment'] ?? '';
            if ($firstComment) $previewComment = $firstComment;
        }
        $canDelete = ($user['role'] === 'super_admin' || (int)$r['created_by'] === (int)$user['id']);
    ?>
    <div class="report-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
            <div style="flex:1;min-width:0">
                <div class="report-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="report-meta">
                    <?php foreach ($rSections as $sec): ?>
                    <span class="badge badge-<?= $sec ?>"><?= $sec ?></span>
                    <?php endforeach; ?>
                    <span>By <?= htmlspecialchars($r['creator']) ?></span>
                    <span data-ts="<?= gmdate('c', strtotime($r['created_at'])) ?>"><?= date('M j, Y \a\t H:i', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if ($previewComment): ?>
                <div class="report-comment"><?= htmlspecialchars($previewComment) ?></div>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
                <?php if ($r['pdf_path']): ?>
                <a href="/exports/<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank"
                   class="btn btn-primary" style="font-size:12px;padding:6px 14px">Download PDF</a>
                <?php elseif ($canSave): ?>
                <button class="btn btn-secondary" style="font-size:12px;padding:6px 14px"
                        id="genbtn-<?= $r['id'] ?>"
                        onclick="genPDF(<?= $r['id'] ?>, this)">Generate PDF</button>
                <div class="progress-bar-wrap" id="prog-<?= $r['id'] ?>">
                    <div class="progress-bar" id="progbar-<?= $r['id'] ?>"></div>
                </div>
                <?php endif; ?>

                <?php if ($canDelete): ?>
                <button class="btn btn-danger" style="font-size:12px;padding:6px 14px"
                        onclick="deleteReport(<?= $r['id'] ?>,this)">Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($canSave && $availSections): ?>
<!-- ── Save Report Modal ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="saveModal">
    <div class="modal">
        <h2>Save New Report</h2>
        <div id="modal-error" style="display:none;margin-bottom:12px;padding:10px 12px;background:#2a1a1a;border:1px solid #6b2a2a;border-radius:6px;font-size:13px;color:#f87171"></div>

        <div class="modal-body">
            <!-- Report Name -->
            <div class="form-group">
                <label class="form-label" for="reportName">Report Name</label>
                <input type="text" id="reportName" placeholder="e.g. Q1 2026 Full Analytics">
            </div>

            <!-- Section toggles -->
            <div class="form-group">
                <div class="form-label" style="margin-bottom:8px">Sections to Include</div>
                <div id="sectionToggles" style="display:flex;flex-direction:column;gap:6px">
                    <?php foreach ($availSections as $sk):
                        $slabels = ['visitor'=>'Visitor Analytics','performance'=>'Performance Analytics','behavioral'=>'Behavioral Analytics'];
                    ?>
                    <label class="sec-toggle" id="togwrap-<?= $sk ?>" onclick="toggleSection('<?= $sk ?>', event)">
                        <input type="checkbox" id="sec-<?= $sk ?>" value="<?= $sk ?>">
                        <span class="sec-toggle-label"><?= $slabels[$sk] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Per-section config panels (injected by JS) -->
            <div id="sectionPanels"></div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px;flex-shrink:0">
            <button class="btn btn-primary" id="saveReportBtn" onclick="saveReport()">Save Report</button>
            <button class="btn btn-secondary" onclick="closeSaveModal()">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── Chart definitions per section ─────────────────────────────────────────
const SECTION_CHARTS = {
    visitor: [
        {id:'langChart',      label:'Sessions by Browser Language'},
        {id:'pageChart',      label:'Sessions by Page'},
        {id:'sessionChart',   label:'Daily Unique Sessions'},
        {id:'networkChart',   label:'Network Connection Types'},
        {id:'screenChart',    label:'Screen Width Distribution'},
        {id:'fpDevTypeChart', label:'Device Types (Fingerprint)'},
        {id:'fpBrowserChart', label:'Browser Distribution (Fingerprint)'},
        {id:'fpOSChart',      label:'Operating Systems (Fingerprint)'},
        {id:'fpMakeChart',    label:'Device Makes (Fingerprint)'},
        {id:'fpCoresChart',   label:'CPU Core Count (Fingerprint)'},
        {id:'fpGPUChart',     label:'GPU Renderer (Fingerprint)'},
    ],
    performance: [
        {id:'avgLoadChart', label:'Avg Load Time by Page'},
        {id:'dailyChart',   label:'Daily Avg Load Time'},
        {id:'bucketChart',  label:'Load Time Distribution'},
        {id:'timingChart',  label:'Timing Breakdown by Page'},
    ],
    behavioral: [
        {id:'eventTypeChart',   label:'Event Type Distribution'},
        {id:'dailyEventsChart', label:'Daily Event Volume'},
        {id:'errorChart',       label:'JS Errors by Page'},
        {id:'engagementChart',  label:'Engagement Breakdown'},
    ],
};
const SECTION_LABELS = {
    visitor:'Visitor Analytics', performance:'Performance Analytics', behavioral:'Behavioral Analytics'
};

// ── Modal open/close ──────────────────────────────────────────────────────
function openSaveModal() {
    document.getElementById('modal-error').style.display = 'none';
    document.getElementById('reportName').value = '';
    // Reset all checkboxes and panels
    document.querySelectorAll('#sectionToggles input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        document.getElementById('togwrap-' + cb.value)?.classList.remove('active');
    });
    document.getElementById('sectionPanels').innerHTML = '';
    document.getElementById('saveModal').classList.add('open');
}
function closeSaveModal() {
    document.getElementById('saveModal').classList.remove('open');
}

// ── Toggle a section's config panel ──────────────────────────────────────
function toggleSection(key, e) {
    // Prevent double-fire when clicking the checkbox itself
    if (e.target.tagName === 'INPUT') {
        setTimeout(() => _applyToggle(key), 0);
    } else {
        const cb = document.getElementById('sec-' + key);
        cb.checked = !cb.checked;
        _applyToggle(key);
    }
}

function _applyToggle(key) {
    const cb      = document.getElementById('sec-' + key);
    const wrap    = document.getElementById('togwrap-' + key);
    const panels  = document.getElementById('sectionPanels');

    wrap.classList.toggle('active', cb.checked);

    if (cb.checked) {
        if (!document.getElementById('panel-' + key)) {
            panels.appendChild(buildSectionPanel(key));
        }
        document.getElementById('panel-' + key).classList.add('open');
    } else {
        const p = document.getElementById('panel-' + key);
        if (p) { p.classList.remove('open'); setTimeout(() => p.remove(), 200); }
    }
}

// ── Build a section config panel ──────────────────────────────────────────
function buildSectionPanel(key) {
    const charts  = SECTION_CHARTS[key] || [];
    const div     = document.createElement('div');
    div.className = 'sec-panel';
    div.id        = 'panel-' + key;

    const chartCheckboxes = charts.map(c =>
        `<label class="chart-cb-label">
            <input type="checkbox" class="chart-cb" data-section="${key}" value="${c.id}" checked> ${c.label}
        </label>`
    ).join('');

    div.innerHTML = `
        <div style="display:flex;align-items:center;margin-bottom:6px">
            <div style="font-size:13px;font-weight:700;color:#4f8ef7">${SECTION_LABELS[key]}</div>
            <span style="margin-left:auto;font-size:11px;color:#5a7090">Charts to capture</span>
        </div>
        ${charts.length ? `
        <div class="chart-grid">${chartCheckboxes}</div>
        <div style="display:flex;gap:8px;margin-bottom:10px">
            <a class="toggle-all-link" onclick="toggleAllCharts('${key}',true)">Select all</a>
            <a class="toggle-all-link" onclick="toggleAllCharts('${key}',false)">None</a>
        </div>` : '<div style="font-size:12px;color:#5a7090;margin-bottom:10px">No charts defined for this section.</div>'}
        <div class="include-data-row">
            <input type="checkbox" id="incdata-${key}" class="include-data-cb" data-section="${key}" checked>
            <label for="incdata-${key}" style="cursor:pointer">Include data table in PDF</label>
        </div>
        <div class="form-group" style="margin-bottom:6px">
            <label class="form-label">Analyst Commentary</label>
            <textarea id="comment-${key}" class="comment-ta" data-section="${key}" rows="4"
                placeholder="Write your analysis for the ${SECTION_LABELS[key]} section..."></textarea>
        </div>
    `;
    return div;
}

function toggleAllCharts(key, checked) {
    document.querySelectorAll(`.chart-cb[data-section="${key}"]`).forEach(cb => cb.checked = checked);
}

// ── Save report ───────────────────────────────────────────────────────────
function saveReport() {
    const name    = document.getElementById('reportName').value.trim();
    const errEl   = document.getElementById('modal-error');
    const btn     = document.getElementById('saveReportBtn');

    const showErr = msg => { errEl.textContent = msg; errEl.style.display = 'block'; };
    errEl.style.display = 'none';

    if (!name) { showErr('Report name is required.'); return; }

    // Collect selected sections
    const checkedCbs = [...document.querySelectorAll('#sectionToggles input:checked')];
    if (!checkedCbs.length) { showErr('Select at least one section.'); return; }

    const sections = [];
    for (const cb of checkedCbs) {
        const key = cb.value;
        const chartIds = [...document.querySelectorAll(`.chart-cb[data-section="${key}"]:checked`)]
                            .map(c => c.value);
        const includeData = document.getElementById('incdata-' + key)?.checked !== false;
        const comment     = document.getElementById('comment-' + key)?.value.trim() || '';
        sections.push({key, chartIds, includeData, comment});
    }

    const primarySection = sections[0].key;
    const primaryComment = sections[0].comment;

    // Combine all section comments for the analyst_comments column (preview)
    const combinedComments = sections
        .filter(s => s.comment)
        .map(s => sections.length > 1 ? `[${SECTION_LABELS[s.key]}]\n${s.comment}` : s.comment)
        .join('\n\n');

    btn.textContent = 'Saving…';
    btn.disabled = true;

    fetch('/api2/reports.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save_report',
            name,
            section: primarySection,
            analyst_comments: combinedComments,
            config_json: JSON.stringify({sections}),
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = 'Save Report';
        btn.disabled = false;
        if (data.ok) { closeSaveModal(); location.reload(); }
        else showErr(data.error || 'Save failed.');
    })
    .catch(() => { btn.textContent = 'Save Report'; btn.disabled = false; showErr('Network error.'); });
}

// ── Generate PDF via iframe chart capture ─────────────────────────────────
async function genPDF(reportId, btn) {
    const progWrap = document.getElementById('prog-' + reportId);
    const progBar  = document.getElementById('progbar-' + reportId);

    const setProgress = (pct, label) => {
        btn.textContent = label;
        if (progWrap) { progWrap.style.display = 'block'; progBar.style.width = pct + '%'; }
    };

    btn.disabled = true;
    setProgress(5, 'Loading config…');

    try {
        // 1. Fetch report config
        const resp = await fetch('/api2/reports.php?id=' + reportId);
        const data = await resp.json();
        if (!data.ok) throw new Error(data.error || 'Failed to load report config');

        const report = data.data;
        let sections;
        if (report.config_json) {
            sections = JSON.parse(report.config_json).sections;
        } else {
            // Legacy: no config_json, no chart capture
            sections = [{key: report.section, chartIds: [], includeData: true, comment: report.analyst_comments || ''}];
        }

        // 2. Capture charts from each section's page via hidden iframe
        const sectionPayloads = [];
        const totalSecs = sections.length;
        for (let i = 0; i < totalSecs; i++) {
            const sec = sections[i];
            const pct = 10 + Math.round((i / totalSecs) * 60);
            setProgress(pct, `Capturing ${SECTION_LABELS[sec.key] || sec.key}… (${i+1}/${totalSecs})`);
            const charts = await captureSection(sec.key, sec.chartIds || []);
            sectionPayloads.push({
                section:     sec.key,
                charts,
                comment:     sec.comment     || '',
                includeData: sec.includeData !== false,
            });
        }

        // 3. Generate multi-section PDF
        setProgress(78, 'Generating PDF…');
        const pdfResp = await fetch('/api2/export_multi.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                sections: sectionPayloads,
                reportId,
                title:       report.name,
                aiAnalysis:  false,
            })
        });
        const pdfData = await pdfResp.json();
        if (!pdfData.ok) throw new Error(pdfData.error || 'PDF generation failed');

        setProgress(100, 'Done!');
        setTimeout(() => {
            btn.outerHTML = `<a href="${pdfData.url}" target="_blank"
                class="btn btn-primary" style="font-size:12px;padding:6px 14px">Download PDF</a>`;
            if (progWrap) progWrap.style.display = 'none';
        }, 400);

    } catch (e) {
        btn.textContent = 'Generate PDF';
        btn.disabled = false;
        if (progWrap) progWrap.style.display = 'none';
        alert('Error generating PDF: ' + e.message);
    }
}

// ── Iframe-based chart capture ────────────────────────────────────────────
function captureSection(sectionKey, chartIds) {
    return new Promise(resolve => {
        if (!chartIds || !chartIds.length) { resolve([]); return; }

        const iframe = document.createElement('iframe');
        iframe.style.cssText = [
            'position:fixed', 'left:-9999px', 'top:-9999px',
            'width:1280px', 'height:900px',
            'visibility:hidden', 'border:none', 'z-index:-1',
        ].join(';');
        document.body.appendChild(iframe);

        const cleanup = () => { try { document.body.removeChild(iframe); } catch(_) {} };
        const timeout = setTimeout(() => { cleanup(); resolve([]); }, 22000);

        iframe.onload = function() {
            // Wait for Chart.js animations to complete (default 1000ms + buffer)
            setTimeout(() => {
                clearTimeout(timeout);
                const charts = [];
                const defs   = SECTION_CHARTS[sectionKey] || [];

                chartIds.forEach(id => {
                    const def = defs.find(c => c.id === id);
                    if (!def) return;
                    try {
                        const doc    = iframe.contentDocument || iframe.contentWindow.document;
                        const canvas = doc.getElementById(id);
                        if (!canvas || !canvas.width) return;

                        // Composite onto white background
                        const tmp = doc.createElement('canvas');
                        tmp.width  = canvas.width;
                        tmp.height = canvas.height;
                        const ctx  = tmp.getContext('2d');
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, tmp.width, tmp.height);
                        ctx.drawImage(canvas, 0, 0);
                        charts.push({label: def.label, imageData: tmp.toDataURL('image/jpeg', 0.88)});
                    } catch(err) {
                        console.warn('Could not capture chart:', id, err);
                    }
                });

                cleanup();
                resolve(charts);
            }, 1600); // 1.6s for chart animations
        };

        iframe.onerror = function() { clearTimeout(timeout); cleanup(); resolve([]); };
        iframe.src = '/reports/' + sectionKey + '.php';
    });
}

// ── Delete report ─────────────────────────────────────────────────────────
function deleteReport(id, btn) {
    if (!confirm('Delete this report? This cannot be undone.')) return;
    btn.disabled = true;
    fetch('/api2/reports.php?id=' + id, {method: 'DELETE'})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const card = btn.closest('.report-card');
                card.style.transition = 'opacity .3s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            } else {
                btn.disabled = false;
                alert('Error: ' + (data.error || 'unknown'));
            }
        });
}

// Close modal on backdrop click
document.getElementById('saveModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSaveModal();
});
</script>
</body>
</html>
