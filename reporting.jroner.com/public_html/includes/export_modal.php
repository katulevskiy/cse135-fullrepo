<?php
/**
 * Shared Export PDF Modal
 * Requires: $section (string), $chartConfig (array of ['id'=>'canvasId','label'=>'Chart Name'])
 * Must be included AFTER Chart.js is loaded and charts are instantiated.
 */
?>
<!-- ===== Export Modal ===== -->
<div id="exportModal" style="
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
    z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="
      background:#1a2133;border:1px solid #2a3448;border-radius:12px;
      padding:32px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">

    <h2 style="font-size:17px;font-weight:600;color:#f0f4ff;margin-bottom:4px">Export PDF Report</h2>
    <p style="font-size:12px;color:#5a7090;margin-bottom:24px">
        Section: <strong style="color:#94a3b8"><?= htmlspecialchars(ucfirst($section)) ?></strong>
    </p>

    <!-- Charts -->
    <div style="margin-bottom:20px">
      <div style="font-size:11px;font-weight:700;color:#7a8fa6;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Charts to Include</div>
      <div id="chartChecks" style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($chartConfig as $chart): ?>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:6px;border:1px solid #2a3448;transition:border-color .12s"
               onmouseover="this.style.borderColor='#3a4a60'" onmouseout="this.style.borderColor='#2a3448'">
          <input type="checkbox" value="<?= htmlspecialchars($chart['id']) ?>"
                 data-label="<?= htmlspecialchars($chart['label']) ?>"
                 class="chart-cb" checked
                 style="accent-color:#4f8ef7;width:15px;height:15px;flex-shrink:0">
          <span style="font-size:13px;color:#c8d6e8"><?= htmlspecialchars($chart['label']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Data table -->
    <div style="margin-bottom:20px">
      <div style="font-size:11px;font-weight:700;color:#7a8fa6;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Data</div>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:6px;border:1px solid #2a3448">
        <input type="checkbox" id="includeDataCb" checked
               style="accent-color:#4f8ef7;width:15px;height:15px;flex-shrink:0">
        <span style="font-size:13px;color:#c8d6e8">Include raw data table</span>
      </label>
    </div>

    <!-- AI Analytics -->
    <div style="margin-bottom:24px">
      <div style="font-size:11px;font-weight:700;color:#7a8fa6;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">AI Analytics</div>
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px;border-radius:6px;border:1px solid #2a3448;transition:border-color .12s"
             onmouseover="this.style.borderColor='#4f8ef7'" onmouseout="this.style.borderColor='#2a3448'">
        <input type="checkbox" id="aiCb"
               style="accent-color:#4f8ef7;width:15px;height:15px;flex-shrink:0;margin-top:1px">
        <div>
          <div style="font-size:13px;color:#c8d6e8;font-weight:500">✨ Include AI Analytics</div>
          <div style="font-size:11px;color:#5a7090;margin-top:3px;line-height:1.5">
            Sends an anonymized data summary to an LLM via OpenRouter.<br>
            Adds an AI-generated insights section at the end of the PDF.<br>
            Adds ~10–15 seconds to generation time.
          </div>
        </div>
      </label>
    </div>

    <div id="exportError" style="display:none;background:#2d1a1a;border:1px solid #7c2d2d;color:#f87171;font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px"></div>

    <div style="display:flex;gap:10px">
      <button id="genPDFBtn" onclick="doExportPDF('<?= htmlspecialchars($section) ?>')"
              style="flex:1;background:#4f8ef7;color:#fff;border:none;border-radius:6px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;transition:background .12s"
              onmouseover="this.style.background='#3b7de8'" onmouseout="this.style.background='#4f8ef7'">
        Generate PDF
      </button>
      <button onclick="closeExportModal()"
              style="background:#2a3448;color:#c8d6e8;border:none;border-radius:6px;padding:11px 20px;font-size:14px;cursor:pointer;transition:background .12s"
              onmouseover="this.style.background='#344057'" onmouseout="this.style.background='#2a3448'">
        Cancel
      </button>
    </div>
  </div>
</div>

<script>
function openExportModal() {
    document.getElementById('exportError').style.display = 'none';
    document.getElementById('exportModal').style.display = 'flex';
}
function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}
document.getElementById('exportModal').addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

async function doExportPDF(section) {
    const btn = document.getElementById('genPDFBtn');
    const errEl = document.getElementById('exportError');
    errEl.style.display = 'none';

    // Collect selected charts → capture canvas with white background as JPEG
    const charts = [];
    document.querySelectorAll('#chartChecks .chart-cb:checked').forEach(cb => {
        const canvas = document.getElementById(cb.value);
        if (!canvas) return;
        // Composite onto a white background so dark canvas CSS doesn't bleed
        // into the PDF as a black rectangle (JPEG has no alpha channel)
        const tmp = document.createElement('canvas');
        tmp.width  = canvas.width;
        tmp.height = canvas.height;
        const ctx = tmp.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, tmp.width, tmp.height);
        ctx.drawImage(canvas, 0, 0);
        const imageData = tmp.toDataURL('image/jpeg', 0.92);
        charts.push({ label: cb.dataset.label, imageData });
    });

    const includeData = document.getElementById('includeDataCb').checked;
    const aiAnalysis  = document.getElementById('aiCb').checked;

    btn.textContent = aiAnalysis ? 'Analyzing + Generating…' : 'Generating…';
    btn.disabled = true;

    try {
        const resp = await fetch('/api2/export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ section, charts, includeData, aiAnalysis })
        });
        const data = await resp.json();
        btn.textContent = 'Generate PDF';
        btn.disabled = false;

        if (data.ok && data.url) {
            closeExportModal();
            window.open(data.url, '_blank');
        } else {
            errEl.textContent = data.error || 'Export failed.';
            errEl.style.display = 'block';
        }
    } catch (e) {
        btn.textContent = 'Generate PDF';
        btn.disabled = false;
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    }
}
</script>
