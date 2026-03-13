# GRADER.md — CSE 135 Analytics Platform

## Login Credentials

| Role | Username | Password | Access |
|---|---|---|---|
| Super Admin | `admin` | `Admin@135!` | Everything: all 3 reports, user management, export, save |
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
3. You should land on the **Saved Reports** page
4. Verify that attempting to access https://reporting.jroner.com/reports/visitor.php redirects you to a **403 Access Denied** page
5. Log out

### Step 2 — Restricted Analyst (sam)
1. Log in as **sam** / `Analyst@135!`
2. You should land on the **Performance Analytics** page (sam's only permitted section)
3. Verify the nav shows only "Performance" and "Saved Reports" (no Visitor, Behavioral, or Admin links)
4. Verify that attempting to access https://reporting.jroner.com/reports/visitor.php returns 403
5. On the Performance page: scroll through the 4 charts and the "Slowest Sessions" table
6. In the "Analyst Commentary" box, type a note about the product-detail.html anomaly and click **Save Commentary**
7. Click **Export PDF** — a PDF should open in a new tab with the data table and your comment
8. Log out

### Step 3 — Full Analyst (sally)
1. Log in as **sally** / `Analyst@135!`
2. You should land on the **Performance Analytics** page (sally's first permitted section)
3. Navigate to **Behavioral Analytics** — confirm access
4. Verify that the Visitor section is NOT accessible (no nav link; attempting the URL gives 403)
5. On the Behavioral page: review the event type distribution chart and the JS error count
6. Go to **Saved Reports** and click **+ Save New Report**
7. Name it "Sally's Behavioral Analysis Q1", pick Behavioral, write a comment, click Save
8. You should see the new report in the list
9. Click **Generate PDF** on that report and download it
10. Log out

### Step 4 — Super Admin Full Walkthrough
1. Log in as **admin** / `Admin@135!`
2. You should land on the **Visitor Analytics** page
3. Review all 5 charts (language, page, daily sessions, network, screen width) and the data table
4. Navigate to **Performance Analytics** — note the KPI cards (avg load, peak, session count) and the stacked timing breakdown chart
5. Navigate to **Behavioral Analytics** — note the JS errors by page bar chart (chaos.js effects visible)
6. Click **Export PDF** on each section to generate PDFs
7. Navigate to **Saved Reports** — you should see the report saved by sally and the Q1 Visitor Overview
8. Go to **Admin** → verify you see all 4 users listed with their roles
9. Click **Add User** → create a new test user (any username, role: analyst, section: visitor)
10. Verify the new user appears in the list; delete it
11. Try https://reporting.jroner.com/nonexistent_page.php — should show the 404 page
12. Log out

---

## Known Issues & Architectural Concerns

### Bugs / Limitations

1. **PDF charts are text-only** — FPDF is a pure-PHP library that cannot execute JavaScript or render Canvas elements. Exported PDFs contain data tables and analyst commentary but no chart images. This is a known trade-off (dompdf would also have this limitation; wkhtmltopdf requires OS-level install). Acknowledged: chart snapshots via `canvas.toDataURL()` was left as future work.

2. **PDF generation within API (self-curl) is unreliable** — When saving a named report via `api2/reports.php`, the endpoint tries to auto-generate a PDF by making an internal curl request to itself. This self-referential HTTP call sometimes fails due to session forwarding issues. Workaround: the "Generate PDF" button on the Saved Reports page works correctly as it's initiated from the client with an active session.

3. **Limited dataset** — With only 46 static records, 45 performance records, and ~250 activity batches (mostly from one session on test.html), many of the charts look sparse. The visualizations are architecturally correct but would be more meaningful with more diverse traffic on test.jroner.com.

4. **No date range filtering** — Reports always show all available data. A date picker for filtering time windows was left as future work.

5. **The original `/api/` endpoints remain unauthenticated** — The REST CRUD API for raw data (activity.php, performance.php, static.php) inherited from HW was not modified (ownership was locked to a different user). These are CORS-restricted to test.jroner.com but have no session-based auth. They should not be used by any user-facing page.

### Architecture Notes

- Sessions use PHP's default file-based storage — fine for a single-server setup but would need Redis/Memcached for multi-server deployment
- Password hashing uses `password_hash()` with `PASSWORD_DEFAULT` (currently bcrypt) — properly future-proofed
- The `exports/` directory is world-writable (chmod 777) because the Apache user (`www-data`) needs to write PDFs. In production, a more restrictive approach (www-data group ownership) would be appropriate
- setup.php was used to create tables and should be deleted from the server post-grading (or kept as documentation — it uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE` so re-running is safe)
