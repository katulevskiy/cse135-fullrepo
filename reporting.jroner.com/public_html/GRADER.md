# GRADER.md — CSE 135 Analytics Platform

## Login Credentials

| Role | Username | Password | Access |
|---|---|---|---|
| Super Admin | `admin` | `Admin@135!` | Everything: all 3 reports, user management, export, save, AI analytics |
| Analyst (Performance + Behavioral) | `sally` | `Analyst@135!` | Performance & Behavioral reports, save reports, export |
| Analyst (Performance only) | `sam` | `Analyst@135!` | Performance report only |
| Viewer | `viewer` | `Viewer@135!` | Saved Reports page only (read-only) |

Login URL: **https://reporting.jroner.com/login.php**

---

## Grading Scenario

Follow these steps to evaluate the system end-to-end:

### Step 1 — Viewer Experience
1. Go to https://reporting.jroner.com/login.php
2. Log in as **viewer** / `Viewer@135!`
3. You should land on the **Saved Reports** page (only page viewers can access)
4. Verify that navigating to https://reporting.jroner.com/reports/visitor.php redirects to a **403 Access Denied** page
5. Log out

### Step 2 — Restricted Analyst (sam)
1. Log in as **sam** / `Analyst@135!`
2. You should land on the **Performance Analytics** page (sam's only permitted section)
3. Verify the nav shows only "Performance" and "Saved Reports" — no Visitor, Behavioral, or Admin links
4. Confirm https://reporting.jroner.com/reports/visitor.php returns 403
5. Scroll through the 4 charts (avg by page, time-series, histogram, timing breakdown) and KPI cards (avg, median, peak load)
6. Note the data-driven analyst commentary pre-filled in the text area — edit it and click **Save Commentary**
7. Click **Export PDF** → modal appears → select which charts to include → click Generate
8. Confirm a PDF opens in a new tab with: commentary first, then summary, then chart images (2 per row, light backgrounds), then data table
9. Log out

### Step 3 — Full Analyst (sally)
1. Log in as **sally** / `Analyst@135!`
2. Navigate to **Behavioral Analytics** — confirm access
3. Confirm the Visitor section is NOT accessible (no nav link; URL gives 403)
4. Review the event type chart, JS errors by page bar chart, and engagement donut
5. Note the pre-filled behavioral commentary summarizing event composition and error alerts
6. Go to **Saved Reports** → click **+ Save New Report** → name it, pick Behavioral, add a comment, Save
7. Click **Generate PDF** on the saved report — PDF should download correctly
8. Log out

### Step 4 — Super Admin Full Walkthrough
1. Log in as **admin** / `Admin@135!`
2. You should land on the **Visitor Analytics** page

**Visitor page — check these features:**
- 5 session charts: language (bar), page distribution (pie), daily sessions (line), network (donut), screen widths (horizontal bar)
- **World Map** below screen width chart — city-dot markers show geographic IP origins. Hover for city + count tooltip.
- **Device Intelligence section** below the map — KPI cards (unique fingerprints, top browser, top OS, mobile %), 6 charts (device type, browser, OS, device make, CPU cores, GPU renderer), and a detail table with canvas fingerprint hashes
- **Timezone selector** in nav (top right) — change it and watch all table timestamps update live without a page reload
- The analyst commentary textarea is **pre-filled** with a data-driven summary — you can edit and save it

3. Navigate to **Performance Analytics**
   - Review KPI cards (avg / median / peak load), note the median is much lower than the avg (outlier effect from chaos.js)
   - Check the timing breakdown stacked bar chart (DNS / TCP / TTFB / DOM / Load per page)
   - Pre-filled commentary explains the outlier issue automatically

4. Navigate to **Behavioral Analytics**
   - Note the JS error count KPI card and the "JS Errors by Page" chart
   - Pre-filled commentary flags the chaos.js-related errors on product-detail

5. Click **Export PDF** on the **Visitor** page:
   - Modal shows separate checkboxes for all 5 session charts + 4 device fingerprint charts
   - Check ✅ "Include raw data table"
   - Enable ✅ AI Analytics (adds ~10–15s) — result includes an AI insights section at the end
   - Confirm PDF opens with: commentary → summary → 2-per-row chart images (white bg) → data table → device intelligence → AI section

6. Navigate to **Saved Reports** — should show report(s) saved by sally
7. Navigate to **Admin** — verify all 4 users listed with roles and section badges
8. Click **Add User** → create a test analyst with visitor access → verify it appears → delete it
9. Try https://reporting.jroner.com/nonexistent.php — should show the styled **404** page
10. Log out

---

## Known Issues & Architectural Concerns

### Acknowledged Bugs / Limitations

1. **FPDF latin1 character set** — FPDF's built-in fonts only support latin1. Unicode characters (e.g. emoji, accented letters) in commentary text are transliterated with `iconv('UTF-8','latin1//TRANSLIT',...)`. Unusual characters may appear approximated or dropped. Fix: embed a TTF font in FPDF (future work).

2. **Device fingerprint table is seeded with test data** — The 9 fingerprinted sessions in `device_fingerprints` were inserted manually as representative test data, since new visits to test.jroner.com will now auto-populate the table going forward via the updated `collector.js`. The seeded rows intentionally represent a realistic mix of browsers/OSes/devices.

3. **Limited real dataset** — With ~70 static sessions and ~69 performance records (mostly UCSD network), charts are correct architecturally but sparse. The world map shows only US city dots because all resolved IPs are from San Diego / La Jolla. More diverse traffic would enrich every visualization.

4. **No date range filtering** — Reports always show all available data. A date picker for time-windowed reports was scoped as future work.

5. **Self-referential PDF generation in `api2/reports.php`** — When saving a named report, the endpoint attempts to auto-generate a PDF via an internal HTTP call to itself. This sometimes fails due to session forwarding issues. Workaround: use the "Generate PDF" button on the Saved Reports page (client-initiated, fully reliable).

6. **ip-api.com HTTP only** — The free tier of ip-api.com requires HTTP (not HTTPS) for the batch endpoint. The server-side PHP call goes out over HTTP, which is fine for server-to-API calls but means the geo data pipeline would need upgrading to the paid tier for production HTTPS compliance.

7. **chart canvas not exportable on mobile** — If a user accesses the dashboard on a mobile browser where Chart.js canvas elements don't fully initialize before the modal opens, some chart captures may come back blank in the PDF. Reproduced only on very slow connections. Workaround: wait for charts to finish animating before clicking Export PDF.

### Architecture Notes

- **Session storage**: PHP file-based sessions — correct for single-server; would need Redis for multi-server
- **Password hashing**: `password_hash()` with `PASSWORD_DEFAULT` (bcrypt) — properly future-proofed
- **exports/ directory**: chmod 777 to allow Apache (`www-data`) to write PDFs. In production, group-ownership approach (`chown www-data:dan`) would be more restrictive
- **`collector.js` fingerprinting**: Canvas fingerprint is a djb2 hash over `canvas.toDataURL()`. It's a **soft identifier** — same device+browser+GPU = same hash; incognito mode, OS updates, or driver changes will produce a different hash. Not suitable as a hard user identifier, but useful for device diversity analytics
- **OpenRouter API key**: Embedded in `api2/export.php`. In production this should be in an environment variable or secrets manager
- **`setup.php`**: Was used once to create tables and can be safely re-run (all statements use `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`). File has been deleted from the server post-setup
