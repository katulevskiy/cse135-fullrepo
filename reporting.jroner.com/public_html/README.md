# CSE 135 Analytics Platform — Winter 2026

**Team:** Jacob Roner, Benjamin Johnson, Daniil Katulevskiy

## Site Links

| Site | URL |
|---|---|
| Analytics Dashboard | https://reporting.jroner.com |
| Tracked Demo Site | https://test.jroner.com |
| Data Collector | https://collector.jroner.com |
| Team Homepage | https://jroner.com |

## Technical Overview

### Architecture

This is a **pure PHP + vanilla JavaScript** analytics platform with no backend frameworks (no Laravel, Symfony, etc.) and no JavaScript frameworks (no React, Vue, etc.).

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 (procedural, prepared statements) |
| Database | MySQL via `mysqli` extension |
| Frontend | Vanilla JavaScript (ES6+) |
| Charts | Chart.js 4 (CDN, ~50KB gzip) |
| PDF Export | FPDF 1.84 (pure PHP, no extensions needed) |
| Auth | PHP sessions, `password_hash`/`password_verify` |
| Styles | Inline CSS (dark theme, consistent across pages) |
| Server | Apache with mod_rewrite, HTTPS |

### Database: `collector_logs` on localhost

**Data collection tables** (written by `collector.jroner.com`):
- `static` — per-page session metadata (UA, language, screen, network)
- `performance` — PerformanceNavigationTiming entries per page load
- `activity` — batched user interaction events (mouse, click, scroll, keyboard, idle, errors)

**New tables** (created for this final push):
- `users` — user accounts with roles (`super_admin`, `analyst`, `viewer`)
- `analyst_sections` — maps analyst users to their permitted report sections
- `saved_reports` — named reports with analyst commentary, accessible to viewers

### File Structure

```
public_html/
├── includes/
│   ├── auth.php       # Auth helpers: requireLogin, requireRole, requireSection, renderNav
│   └── db.php         # Shared DB connection (singleton)
├── admin/
│   ├── index.php      # User list — super_admin only
│   ├── user_form.php  # Create/edit user + section permissions
│   └── user_delete.php
├── reports/
│   ├── visitor.php    # Visitor/Session analytics (static table)
│   ├── performance.php # Performance analytics (performance table)
│   ├── behavioral.php # Behavioral analytics (activity table)
│   └── saved.php      # Saved reports (all roles)
├── api/               # Original REST CRUD endpoints (static/performance/activity)
├── api2/
│   ├── reports.php    # Save/list/delete named reports + analyst comments
│   └── export.php     # PDF generation using FPDF
├── lib/
│   └── fpdf.php       # FPDF library (pure PHP PDF generation)
├── exports/           # Generated PDF files (web-accessible)
├── login.php          # Session-based login
├── logout.php         # Session destruction
├── index.php          # Landing page (role-based redirect)
├── 403.php            # Styled 403 error page
├── 404.php            # Styled 404 error page
└── .htaccess          # ErrorDocument 403/404, Options -Indexes
```

### Three Report Sections

1. **Visitor/Session Analytics** — from the `static` table
   - Language breakdown (bar), page distribution (pie), daily sessions (line), network types (donut), screen widths (horizontal bar)
   - Data table of last 50 sessions

2. **Performance Analytics** — from the `performance` table
   - Avg load time by page (bar), load time time-series (line), distribution histogram, timing breakdown stacked bar (DNS/TCP/TTFB/DOM/Load)
   - KPI cards: avg load, peak load, session count
   - Slowest 20 sessions table

3. **Behavioral Analytics** — from the `activity` table
   - Event type distribution (bar), daily event volume (line), JS errors by page (bar), engagement breakdown (donut)
   - KPI cards: total events, unique sessions, JS error count
   - Activity batch table with event type badges

### Auth & Roles

| Role | Access |
|---|---|
| `super_admin` | All reports, all sections, user management |
| `analyst` | Assigned sections only (configurable per user), can save reports and comments |
| `viewer` | Saved reports page only (read-only) |

### Export System

PDF exports are generated server-side using **FPDF** (a pure-PHP library requiring no extensions beyond what's in our baseline PHP install). Each PDF contains:
- A summary stats section
- A tabular data dump (up to 80 rows)
- The analyst's commentary

Note: Chart.js charts do not render in PDFs (JS-dependent). The PDF is a complementary data export, not a chart snapshot.

## Use of AI

Cursor AI was used extensively in this project to accelerate implementation:
- Auth system scaffolding and role/section checking logic
- PHP query construction for the three report pages
- PDF export using FPDF with proper table layouts
- FPDF was chosen over dompdf specifically because it has no PHP extension dependencies

**Observation on AI value:**
- Very effective for boilerplate (CSS, HTML structure, repetitive DB queries)
- Required careful review for security-sensitive code (auth checks, prepared statements)
- The suggestion to use dompdf initially failed because `ext-dom` was not available on the server; manual intervention was required to switch to FPDF

## Roadmap (Future Work)

- **Date range filtering** on all three report sections (currently shows all data)
- **Real-time refresh** via Server-Sent Events or polling interval on report pages
- **Per-session drill-down** — click a session ID to see its full event timeline
- **Chart image capture** for PDF exports (using Canvas `toDataURL()` sent server-side)
- **Email export** — send PDF to specified email via PHP `mail()` or SMTP
- **Role-based report visibility** — analysts could mark reports as private or shared
- **More data** — need more traffic on test.jroner.com to make charts more meaningful
