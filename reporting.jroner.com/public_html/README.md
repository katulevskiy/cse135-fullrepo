# CSE 135 Analytics Platform — Winter 2026

**Team:** Jacob Roner, Benjamin Johnson, Daniil Katulevskiy

## Site Links

| Site | URL |
|---|---|
| Analytics Dashboard | https://reporting.jroner.com |
| Tracked Demo Site | https://test.jroner.com |
| Data Collector | https://collector.jroner.com |
| Team Homepage | https://jroner.com |

---

## Technical Overview

### Architecture

Pure **PHP + vanilla JavaScript** — no backend frameworks, no JS frameworks.

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, procedural style, prepared statements throughout |
| Database | MySQL via `mysqli`, single shared connection singleton |
| Frontend | Vanilla JavaScript (ES2020) |
| Charts | Chart.js 4 (CDN, ~50KB gzip) |
| Maps | Leaflet.js 1.9 + CartoDB tiles (loaded lazily on visitor page only) |
| PDF Export | FPDF 1.84 — pure PHP, zero extension dependencies |
| AI Analytics | OpenRouter API (`openai/gpt-4o-mini`) via `file_get_contents` stream context |
| Auth | PHP sessions, `password_hash`/`password_verify` (bcrypt) |
| IP Geolocation | ip-api.com free batch endpoint, cached in `ip_geo` table |
| Device Fingerprinting | Canvas + WebGL + UA parsing, stored in `device_fingerprints` table |
| Styles | Inline CSS dark theme, consistent across all pages |
| Server | Apache with mod_rewrite, HTTPS via Let's Encrypt |

---

### Database: `collector_logs` on localhost

**Data collection tables** (populated by `collector.jroner.com/log.php`):

| Table | Contents |
|---|---|
| `static` | Per-page session metadata: UA, language, screen size, network type, IP |
| `performance` | PerformanceNavigationTiming per page load (DNS, TCP, TTFB, DOM, load) |
| `activity` | Batched user interaction events (mousemove, click, scroll, keydown, idle, errors) |
| `device_fingerprints` | Canvas fingerprint hash, device type/make, OS, browser, CPU cores, RAM, pixel ratio, WebGL GPU info |

**Auth & reporting tables** (created for this final push):

| Table | Contents |
|---|---|
| `users` | Accounts with role (`super_admin`, `analyst`, `viewer`) and bcrypt password |
| `analyst_sections` | Maps analyst users to their permitted report sections |
| `saved_reports` | Named reports with analyst commentary text, created by analysts |
| `ip_geo` | IP → country/city/lat/lng cache (resolved via ip-api.com, never re-queried) |

---

### File Structure

```
reporting.jroner.com/public_html/
├── includes/
│   ├── auth.php          # requireLogin/requireRole/requireSection, renderNav, pageHead
│   │                     # nav includes timezone selector (localStorage-backed)
│   ├── db.php            # getDb() — singleton mysqli connection
│   └── export_modal.php  # Shared PDF export modal (charts + options + AI toggle)
├── admin/
│   ├── index.php         # User list — super_admin only
│   ├── user_form.php     # Create/edit user + section permission checkboxes
│   └── user_delete.php
├── reports/
│   ├── visitor.php       # Visitor analytics: 5 session charts + world map + device intelligence
│   ├── performance.php   # Performance: 4 charts + KPI cards (avg/median/peak)
│   ├── behavioral.php    # Behavioral: 4 charts + engagement + JS error breakdown
│   └── saved.php         # Saved reports list (all roles, read-only for viewers)
├── api2/
│   ├── reports.php       # POST save / GET list / DELETE saved reports + comments
│   └── export.php        # PDF generation (FPDF): commentary → summary → charts → data → device intel → AI
├── lib/
│   └── fpdf.php + font/  # FPDF 1.84 library
├── exports/              # Generated PDF files (777 permissions for www-data write)
├── login.php / logout.php
├── index.php             # Landing page — redirects by role
├── 403.php / 404.php     # Styled error pages matching dark theme
└── .htaccess             # ErrorDocument 403/404, Options -Indexes

collector.jroner.com/public_html/
├── collector.js          # Full analytics collector: static + performance + activity + fingerprint
└── log.php               # Receives all event types, inserts to MySQL
```

---

### Report Sections (all three with charts, data tables, analyst comments, PDF export)

**1. Visitor / Session Analytics** (`static` table)
- Charts: language bar, page pie, daily sessions line, network donut, screen width bar
- World map: Leaflet city-dot markers from IP geolocation (auto-cached in `ip_geo`)
- **Device Intelligence**: canvas fingerprint hash, device type/make/OS/browser/CPU/GPU — 6 charts + detail table
- Data-driven default analyst commentary auto-populates when none is saved yet

**2. Performance Analytics** (`performance` table)
- Charts: avg load by page (bar), load time over time (line), histogram, timing breakdown stacked bar
- KPI cards: average, median, and peak load times (color-coded green/yellow/red)
- Data-driven default commentary auto-populates

**3. Behavioral Analytics** (`activity` table)
- Charts: event type distribution, daily event volume, JS errors by page, engagement donut
- KPI cards: total events, unique sessions, JS error count
- Data-driven default commentary auto-populates

---

### PDF Export System

Triggered from the **Export PDF** button on any report page. A modal lets you choose:
- Which charts to include (all checked by default; fingerprint charts shown on visitor page)
- Whether to include the raw data table
- Whether to include the **Device Intelligence** section (visitor only)
- Whether to run **AI Analytics** (sends anonymized summary to OpenRouter GPT-4o-mini, ~10–15s)

PDF structure (in order):
1. Analyst Commentary (at the top, if any)
2. Summary KPI stats
3. Selected charts — rendered **2-per-row** with white backgrounds (canvas composited before capture)
4. Raw data table (up to 80 rows)
5. Device Intelligence section (visitor only, includes browser/OS breakdown + fingerprint table)
6. AI Analytics section (if requested)

Charts are captured client-side via `canvas.toDataURL()` with a white background composite, then base64-sent to the server and embedded as JPEG images in FPDF.

---

### Auth & Roles

| Role | Access |
|---|---|
| `super_admin` | All reports, all sections, export, save, user management (CRUD) |
| `analyst` | Assigned sections only (configurable per user), save reports, export, AI analytics |
| `viewer` | Saved Reports page only — read-only, no export, no charts |

---

### Timezone Support

All timestamp displays across the dashboard update dynamically based on a timezone selector in the nav bar. The selector defaults to the visitor's **local timezone** (detected via `Intl.DateTimeFormat`). Selection is saved in `localStorage` and persists across page loads. All timestamp `<td>` elements carry a `data-ts` UTC ISO attribute; conversion happens in JavaScript.

---

## Use of AI

**Cursor AI** was used extensively to accelerate implementation:
- Full authentication system with role/section guards
- FPDF PDF generation with 2-per-row chart layout and white background compositing
- Canvas fingerprinting (djb2 hash over toDataURL, WebGL unmasked renderer, UA parsing)
- IP geolocation caching pipeline (ip-api.com batch → MySQL cache)
- Leaflet world map integration
- Timezone-aware timestamp display system
- AI analytics integration (OpenRouter API via `file_get_contents` stream context — `curl` unavailable on this server)
- Data-driven auto-generated analyst commentary for all three report sections

**Observations on AI value:**
- Very effective for boilerplate (repeated HTML/CSS patterns, DB query construction)
- Required careful human review for security-critical code (auth guards, prepared statement types)
- Initial suggestion of `dompdf` had to be manually overridden when `ext-dom` was found unavailable; switch to FPDF required no extensions
- AI-suggested `curl_init()` for OpenRouter had to be replaced with `file_get_contents` + stream context when `ext-curl` was also found unavailable
- The iteration cadence (write → test → identify error → fix) was faster with AI but still required judgment at each step

---

## Roadmap (Future Work)

- **Date range filtering** on all three report sections (currently shows all available data)
- **Real-time refresh** via Server-Sent Events or polling on report pages
- **Per-session drill-down** — click a session ID to see full event timeline + heatmap
- **Email export** — send PDF via SMTP/PHP mail
- **Role-based report visibility** — analysts mark reports private or shared
- **More diverse traffic** — test.jroner.com traffic is currently UCSD-centric; real-world data would enrich the device and geographic charts significantly
- **Font embedding in FPDF** — currently using latin1 transliteration; TTF fonts would handle Unicode properly
- **Canvas fingerprint cross-session tracking** — correlate returning visitors by fingerprint hash
