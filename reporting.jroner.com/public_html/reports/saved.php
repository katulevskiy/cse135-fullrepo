<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db   = getDb();
$user = getCurrentUser();

// All saved reports (exclude _comment entries)
$reports = $db->query("
    SELECT r.id, r.name, r.section, r.analyst_comments, r.html_path, r.pdf_path, r.created_at, u.username as creator
    FROM saved_reports r
    JOIN users u ON u.id = r.created_by
    WHERE r.name != '_comment'
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$canSave = in_array($user['role'], ['super_admin', 'analyst']);

pageHead('Saved Reports', '
    .report-card { background: #1a2133; border: 1px solid #2a3448; border-radius: 10px; padding: 20px 24px; margin-bottom: 14px; }
    .report-card:hover { border-color: #3a4a60; }
    .report-name { font-size: 16px; font-weight: 600; color: #f0f4ff; margin-bottom: 6px; }
    .report-meta { font-size: 12px; color: #5a7090; display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
    .report-comment { font-size: 13px; color: #94a3b8; line-height: 1.6; padding: 12px; background: #131d2e; border-radius: 6px; border-left: 3px solid #4f8ef7; white-space: pre-wrap; }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:100; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal { background:#1a2133; border:1px solid #2a3448; border-radius:10px; padding:32px; width:100%; max-width:500px; }
    .modal h2 { font-size:18px;font-weight:600;color:#f0f4ff;margin-bottom:16px }
    .form-group { margin-bottom:16px }
    .form-label { display:block;font-size:12px;font-weight:600;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px }
    input[type=text],select { width:100%;background:#0f1623;border:1px solid #2a3448;border-radius:6px;padding:10px 13px;font-size:14px;color:#e2e8f0;outline:none }
    input[type=text]:focus,select:focus { border-color:#4f8ef7 }
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
        <?php if ($canSave): ?>
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
    <?php foreach ($reports as $r): ?>
    <div class="report-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
            <div style="flex:1">
                <div class="report-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="report-meta">
                    <span><span class="badge badge-<?= $r['section'] ?>"><?= $r['section'] ?></span></span>
                    <span>By <?= htmlspecialchars($r['creator']) ?></span>
                    <span><?= date('M j, Y \a\t H:i', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if ($r['analyst_comments']): ?>
                <div class="report-comment"><?= htmlspecialchars($r['analyst_comments']) ?></div>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
                <?php if ($r['pdf_path']): ?>
                <a href="/exports/<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank" class="btn btn-primary" style="font-size:12px;padding:6px 14px">Download PDF</a>
                <?php elseif ($canSave): ?>
                <button class="btn btn-secondary" style="font-size:12px;padding:6px 14px" onclick="genPDF('<?= $r['section'] ?>','<?= $r['id'] ?>',this)">Generate PDF</button>
                <?php endif; ?>

                <?php if ($user['role'] === 'super_admin' || (int)$r['created_by'] === (int)$user['id']): ?>
                <?php
                // Get creator id for this report
                $stmtCreator = $db->prepare("SELECT created_by FROM saved_reports WHERE id=?");
                $stmtCreator->bind_param("i", $r['id']);
                $stmtCreator->execute();
                $creatorRow = $stmtCreator->get_result()->fetch_assoc();
                if ($user['role'] === 'super_admin' || (int)$creatorRow['created_by'] === (int)$user['id']):
                ?>
                <button class="btn btn-danger" style="font-size:12px;padding:6px 14px" onclick="deleteReport(<?= $r['id'] ?>,this)">Delete</button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($canSave): ?>
<!-- Save Report Modal -->
<div class="modal-overlay" id="saveModal">
    <div class="modal">
        <h2>Save New Report</h2>
        <div id="modal-error" style="display:none" class="alert alert-error"></div>
        <div class="form-group">
            <label class="form-label" for="reportName">Report Name</label>
            <input type="text" id="reportName" placeholder="e.g. Q1 2026 Visitor Summary">
        </div>
        <div class="form-group">
            <label class="form-label" for="reportSection">Section</label>
            <select id="reportSection">
                <?php if (canSeeSection('visitor')): ?><option value="visitor">Visitor</option><?php endif; ?>
                <?php if (canSeeSection('performance')): ?><option value="performance">Performance</option><?php endif; ?>
                <?php if (canSeeSection('behavioral')): ?><option value="behavioral">Behavioral</option><?php endif; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="reportComment">Analyst Commentary</label>
            <textarea id="reportComment" style="height:120px" placeholder="Write your analysis and interpretation of the data..."></textarea>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button class="btn btn-primary" id="saveReportBtn" onclick="saveReport()">Save Report</button>
            <button class="btn btn-secondary" onclick="closeSaveModal()">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function openSaveModal() { document.getElementById('saveModal').classList.add('open'); }
function closeSaveModal() { document.getElementById('saveModal').classList.remove('open'); }

function saveReport() {
    const name     = document.getElementById('reportName').value.trim();
    const section  = document.getElementById('reportSection').value;
    const comments = document.getElementById('reportComment').value.trim();
    const errEl    = document.getElementById('modal-error');
    const btn      = document.getElementById('saveReportBtn');

    if (!name) { errEl.textContent = 'Report name is required.'; errEl.style.display = 'block'; return; }
    errEl.style.display = 'none';
    btn.textContent = 'Saving…';
    btn.disabled = true;

    fetch('/api2/reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_report', name, section, analyst_comments: comments })
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = 'Save Report';
        btn.disabled = false;
        if (data.ok) {
            closeSaveModal();
            window.location.reload();
        } else {
            errEl.textContent = data.error || 'Save failed.';
            errEl.style.display = 'block';
        }
    })
    .catch(() => { btn.textContent = 'Save Report'; btn.disabled = false; errEl.textContent = 'Network error.'; errEl.style.display = 'block'; });
}

function deleteReport(id, btn) {
    if (!confirm('Delete this report? This cannot be undone.')) return;
    btn.disabled = true;
    fetch('/api2/reports.php?id=' + id, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                btn.closest('.report-card').style.opacity = '0';
                setTimeout(() => btn.closest('.report-card').remove(), 300);
            } else {
                btn.disabled = false;
                alert('Error: ' + (data.error || 'unknown'));
            }
        });
}

function genPDF(section, reportId, btn) {
    btn.textContent = 'Generating…';
    btn.disabled = true;
    fetch('/api2/export.php?section=' + section + '&report_id=' + reportId)
        .then(r => r.json())
        .then(data => {
            if (data.url) {
                btn.outerHTML = '<a href="' + data.url + '" target="_blank" class="btn btn-primary" style="font-size:12px;padding:6px 14px">Download PDF</a>';
            } else {
                btn.textContent = 'Generate PDF';
                btn.disabled = false;
                alert('PDF generation failed: ' + (data.error || 'unknown'));
            }
        })
        .catch(() => { btn.textContent = 'Generate PDF'; btn.disabled = false; });
}

// Close modal on outside click
document.getElementById('saveModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSaveModal();
});
</script>
</body>
</html>
