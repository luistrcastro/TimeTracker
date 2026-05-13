# Time Tracker — Claude Development Guide

This document gives a Claude session full context to continue developing or debugging this project without needing to re-explain history.

---

## Project Overview

A lightweight time tracking web app built for **Luis Felipe Castro** to replace an Excel spreadsheet. Developed by Luis Felipe Castro with the assistance of Claude (Anthropic).

**Core concept:** Log work tasks throughout the day with project, description, start/finish times. Compile entries into a Replicon-compatible summary at end of day.

**This is a public repository.** Never commit sensitive or confidential information — no real project codes, client names, internal ticket numbers, credentials, or personal data. Use generic placeholders in examples and defaults.

**Current version:** v2.2.0 (contractor.html) / v2.2.0 (replicon.html — Replicon API integration)

---

## File Structure

```
TimeTracker.bat                  ← Windows launcher (double-click to start)
TimeTracker-WSL.bat              ← WSL launcher template (fill in WSL_PATH and WSL_DISTRO)
TimeTrackerSystem/
  index.html                     ← Landing page: 2-button launcher (Replicon / Contractor)
  replicon.html                  ← Replicon variant (v2.2.0, ~2100 lines)
  contractor.html                ← Contractor variant (v2.1.0, ~1775 lines)
  common.js                      ← Shared JS (~500 lines): data layer, utilities, theme, modals
  common.css                     ← Shared CSS: all styles for both variants
  server.py                      ← Python HTTP server (~490 lines)
  data-replicon.json             ← Replicon entries (auto-created on first save)
  data-contractor.json           ← Contractor entries (auto-created on first save)
  data-contractor-invoices.json  ← Contractor invoices (auto-created on first invoice save)
  data-contractor-clients.json   ← Contractor client details (auto-created on first client save)
  replicon-credentials.json      ← Replicon session credentials (gitignored, auto-created)
  replicon-projects-cache.json   ← Synced project/task data from Replicon (gitignored)
  replicon-row-map.json          ← Maps projectId:taskId → timesheet row index (gitignored)
  CLAUDE.md                      ← This file
```

**Critical rule:** There is no build system, no bundler, no npm. Everything is vanilla HTML/CSS/JS. Do not introduce dependencies.

### Shared architecture
Each variant defines `window.TT_CONFIG = { api, storageKey }` **before** loading `common.js`. The common data layer reads `TT_CONFIG.api` for the server endpoint and `TT_CONFIG.storageKey` for the localStorage fallback key.

Contractor's `initData` overrides the common version to also run `migrateEntries` after loading. It sets `_onServerRecovery` for post-heartbeat-reconnect migration. `initClients()` and `initInvoices()` are called separately on startup.

### API routes
- `/api/replicon/entries` → `data-replicon.json`
- `/api/contractor/entries` → `data-contractor.json`
- `/api/contractor/invoices` → `data-contractor-invoices.json`
- `/api/contractor/clients` → `data-contractor-clients.json`
- `/api/entries` → `data.json` (legacy backward-compat)
- `GET /api/replicon/credentials` → returns `{ configured, base_url, server_view_state_id, session_id, cookie_set }` (never exposes cookie value)
- `POST /api/replicon/credentials` → saves all 4 credential fields; starts 15-min expiry timer
- `POST /api/replicon/sync` → fetches all projects + tasks from Replicon, writes `replicon-projects-cache.json`
- `GET /api/replicon/cache` → returns `replicon-projects-cache.json` (or `{ projects: [] }`)
- `GET/POST /api/replicon/row-map` → reads/writes `replicon-row-map.json`; POST also merges project/task names into cache
- `POST /api/replicon/submit` → maps rows to Replicon URIs, calls `QueueRequests` (SetDuration + SetComment + Save)

---

Launcher details (Windows bat, WSL setup): [`docs/running.md`](docs/running.md).

---

## Data Layer

### Storage
- **Primary:** `data.json` via Python server API (`GET/POST /api/entries`)
- **Fallback:** `localStorage` key `timetracker_entries_v1` when server is unreachable
- **Heartbeat:** Runs every 3 minutes. Detects server going down (switches to localStorage) and coming back up (migrates localStorage entries back to `data.json`)

### Entry Schema
```json
{
  "id": "uuid-v4",
  "date": "YYYY-MM-DD",
  "project": "1234",
  "subProject": "General",
  "description": "Daily activities",
  "subDescription": "Email, Teams, Meetings",
  "furtherInfo": "",
  "start": "08:00",
  "finish": "08:45",
  "duration": "0:45",
  "logged": false,
  "invoiced": false
}
```

**Notes:**
- `start`/`finish`/`duration` are always stored as 24h `HH:MM` strings internally
- `duration` is always `finish - start` — never stored independently
- `logged` is a boolean — only relevant when description matches the Jira pattern (configurable via Settings tab, stored in `timetracker_jira_pattern`)
- `acc_time` (accumulated time for the day) is **always computed on render**, never stored
- `invoiced` is a boolean (contractor only) — set to `true` by `createInvoice()`, reverted to `false` by `voidInvoice()`; never set directly by the user
- **PROJ mode (replicon only):** when `_repliconMode` is `true`, `project` stores the numeric Replicon project ID (e.g. `"9339"`) instead of the display code (e.g. `"2259"`). `subProject` always stores the task name string. `renderRepliconView()` and `isRowMapped()` handle both formats transparently.

### Invoice Schema (contractor only)
```json
{
  "id": "uuid-v4",
  "number": "INV-0001",
  "createdDate": "YYYY-MM-DD",
  "dueDate": "YYYY-MM-DD",
  "client": "ClientName",
  "entryIds": ["uuid", "uuid"],
  "rate": 85.00,
  "subtotal": 637.50,
  "taxRate": 13,
  "taxAmount": 82.88,
  "total": 720.38,
  "status": "draft",
  "notes": ""
}
```
Status values: `draft | sent | paid | void`

### Company Settings (contractor only)
Stored in `localStorage` key `timetracker_company_settings`:
```json
{
  "name": "", "address": "", "phone": "", "email": "",
  "logo": "",
  "defaultRate": 0, "defaultTaxRate": 0
}
```
Logo stored as base64 data URL. Edited via the Company & Invoicing Defaults card in the Settings tab.

### Client Details (contractor only)
Stored server-side in `data-contractor-clients.json` (falls back to `localStorage` key `timetracker_clients_v2`). Keyed by client name:
```json
{
  "ClientName": {
    "id": "uuid-v4",
    "tasks": ["Task A", "Task B"],
    "legalName": "Full Legal Entity Name",
    "address": "123 Client St, City, Province",
    "phone": "+1 555-0100",
    "email": "billing@client.com"
  }
}
```
- `tasks` — auto-populated from entries via `seedClientsFromEntries()`; also added by `addClientTask()` when a new entry is saved
- `legalName`, `address`, `phone`, `email` — billing info shown in the "Bill To" block on printed invoices; edited via the Client Details card in the Settings tab
- `id` — UUID assigned on first creation; back-filled by `_backfillClientIds()` for older records that lack one
- Edited via the "Client Details" card in the Settings tab: select a client from the dropdown, fill fields, click "Save Client"

### Key Data Functions
| Function | Description |
|---|---|
| `loadEntries()` | Returns in-memory `_entries` array (sync) |
| `saveEntries(entries)` | Updates `_entries` and persists (async) |
| `persistEntries()` | Writes to server or localStorage fallback (async) |
| `initData()` | Loads from server on startup, migrates localStorage if needed |
| `heartbeat()` | Runs every 3 min, handles server up/down detection |
| `loadInvoices()` | Returns in-memory `_invoices` array (sync) — contractor only |
| `saveInvoices(arr)` | Updates `_invoices` and persists to server or localStorage — contractor only |
| `initInvoices()` | Loads invoices from server on startup — contractor only |
| `loadClients()` | Returns in-memory `_clients` object (sync) — contractor only |
| `saveClients(obj)` | Updates `_clients` and persists to server or localStorage — contractor only |
| `initClients()` | Loads clients from server on startup, falls back to localStorage — contractor only |
| `addClientTask(clientName, taskName)` | Ensures client exists; adds task if new (async) — contractor only |
| `seedClientsFromEntries()` | Populates `_clients` from existing entries on startup (async) — contractor only |
| `_backfillClientIds()` | Adds missing `id` fields to client records and saves if changed — contractor only |

---

## Frontend Architecture

### Tabs
- **Day View** — main entry table for selected date + inline new-entry row
- **Week View** — Sat–Fri week, grouped by day, read-only (no entry from here)
- **Replicon** — compiled view: entries grouped by Project+SubProject, hours summed, comments concatenated
- **Settings** — configuration (Jira pattern), export JSON, export CSV, import JSON, stats; also contains Company & Invoicing Defaults and Client Details sections (contractor only)
- **Invoicing** — (contractor only) create invoices from uninvoiced entries, view/manage past invoices, print to PDF

### State Variables
```javascript
let currentDate        // "YYYY-MM-DD" — shared across all tabs
let _entries           // in-memory cache of all entries
let _usingServer       // bool — true if server is reachable
let _sortCol           // 'project' | 'subProject' | 'start' | null
let _sortDir           // 'asc' | 'desc' | null
let _darkMode          // bool
let _use12h            // bool
let _deletedEntry      // last deleted entry for undo
let _undoTimeout       // timeout handle for undo toast
let _jiraPattern       // regex string, loaded from localStorage key timetracker_jira_pattern
let JIRA_RE            // RegExp built from _jiraPattern

// replicon.html only:
let _repliconCache     // projects/tasks cache loaded from /api/replicon/cache
let _repliconMode      // bool — true = PROJ mode (select dropdowns), false = FREE mode (text inputs)
let _repliconCredsOk   // bool — true only when all 4 credential fields are present on server
```

### Render Functions
| Function | Description |
|---|---|
| `renderAll()` | Calls renderDayView + visible tab renders + header date |
| `renderDayView(prefillStart?)` | Rebuilds entire day table including new-entry row |
| `renderWeekView()` | Builds Sat–Fri week blocks |
| `renderRepliconView()` | Builds compiled Replicon table |
| `renderStats()` | Updates Settings tab stats |
| `populateSettingsTab()` | Fills Settings tab inputs (Jira pattern) from current state; also calls `populateCompanySettings()` in contractor |
| `renderInvoiceTab()` | Pre-fills rate/tax defaults from company settings, wires autocomplete — contractor only |
| `renderInvoiceList()` | Rebuilds invoice table sorted by date descending — contractor only |
| `populateClientSelect()` | Populates the client dropdown in the Client Details card from `_clients` — contractor only |
| `populateClientDetails()` | Fills Client Details form fields from selected client in `_clients` — contractor only |
| `openClientDetailsForClient(name)` | Switches Client Details card to a named client (called from invoice detail) — contractor only |

**Important:** `renderDayView` always computes acc times on **chronological** order first, then applies display sort. Acc Time always reflects chronological order regardless of sort.

### Autocomplete (Project / Sub Project)
- Dropdown shows up to 8 matches from existing entries as you type
- Keyboard nav: **↑ / ↓** to move, **Enter** to select (uses `stopImmediatePropagation` to avoid also triggering the "next field" Enter handler), **Escape** to close
- Active item highlighted via `.autocomplete-item.active`
- `setupAC(inputEl, field, listEl)` wires up input, keydown, and blur handlers
- `inp.autocomplete = 'off'` — disables browser native autocomplete to avoid conflicts
- Always lives at the bottom of the day table (never a floating form)
- Built dynamically by `buildNewRow(prefillStart)` — creates `<tr id="new-entry-row">`
- Field IDs: `nr-project`, `nr-subproject`, `nr-description`, `nr-subdesc`, `nr-info`, `nr-start`, `nr-finish`, `nr-duration` (readonly), `nr-acc` (readonly), `nr-logged`
- Enter key navigates between fields, Enter on Finish saves
- On save: calls `saveNewRow()` → pushes entry → re-renders with Start pre-filled from saved Finish

### Edit Modal
- Triggered by ✎ button on any saved row → `openEdit(id)`
- Required fields: Project, Description, Start, Finish
- Save via `saveModal()` — validates same required fields

### Row Actions (left to right)
✎ Edit → ⧉ Duplicate → ⑃ Split → ⎘ Copy as text → ✕ Delete (with 5s undo toast)

---

## Key Features & Logic

### Jira Detection
- Pattern: configurable regex stored in `localStorage` key `timetracker_jira_pattern` (default `PROJ-\d+`)
- Editable via the Configuration card in the Settings tab; saved immediately on "Save"
- `_jiraPattern` (string) and `JIRA_RE` (RegExp) are rebuilt whenever the user saves a new pattern
- Unlogged Jira rows: amber background (`var(--jira-bg)`), "Needs Jira log" badge
- Once `logged` checkbox is checked: badge disappears, row turns green (`var(--logged-bg)`)
- Logged checkbox is disabled (greyed out) on rows with no Jira ticket

### Time Offset Cascade
- Triggered from the edit modal when the **Finish** time is changed
- **Cascade button** is always visible but disabled by default. Enables as soon as finish differs from original
- Button label updates dynamically: `Cascade +15m to 4 rows` / `Cascade −15m to 2 rows` / `Cascade +15m — no rows after` (disabled)
- **Delta** = new finish − original finish (Option A — finish change only, regardless of start changes)
- **Affected rows** = all same-day entries where `start >= original finish`, excluding the edited row, sorted chronologically
- **Cascade logic:** each affected row's start and finish both shift by delta. Durations are preserved (not recalculated)
- Overflow past midnight is allowed — no capping
- **Two save paths coexist:**
  - `Save Changes` — saves only the edited row, may create gaps/overlaps (existing behaviour unchanged)
  - `Cascade change` — saves edited row + shifts all affected rows

**Key variables:**
- `_originalFinish` — stored in `openEdit()`, reset to `null` in `closeModal()`
- `saveModal(cascade = false)` — accepts boolean; when `true`, applies cascade after saving the edited row

**Future idea (backlog):** selective cascade — checkboxes per row to include/exclude from the cascade

### Gap & Overlap Detection
- Runs on chronological entries before rendering
- **Gap:** `finish` of row N < `start` of row N+1 → amber indicator row inserted between them showing duration (e.g. "⟵ 15m gap ⟶")
- **Overlap:** `finish` of row N > `start` of row N+1 → both rows get red background + "⚠ overlap" badge

### Replicon Compilation
- Groups entries by `project + subProject`
- Sums total minutes → converts to decimal rounded to nearest 0.25
- Comments format: `(0.5) Description - SubDescription, (1) Next task`
- **Copy per row** copies comments only (not project/hours) — ready to paste into Replicon's comments field
- **Copy All** copies full tab-separated rows (project, hours, comments) — ready to paste into Replicon timesheet

### Copy From Another Day
- **"⎘ Copy from..."** button in the Day View topbar opens a modal
- Date nav: `‹` / `›` chevrons shift date by one day; direct date input also available; defaults to yesterday
- Checklist of all entries from the selected date (sorted by start time), showing `project · subProject` and `description — subDescription`
- "Select all / Deselect all" toggle; confirm button label updates dynamically: `Copy selected (2)`
- Copied entries land on `currentDate` with `start: ''`, `finish: ''`, `duration: '0:00'`, `logged: false` — all other fields preserved
- Footer note: "Times will not be copied"

**Key functions:**
- `openCopyFrom()` — sets date to yesterday, renders list, shows modal
- `copyFromChangeDate(delta)` — shifts the modal's date picker by ±1 day
- `renderCopyFromList()` — reads entries for the selected date, builds checkbox list
- `updateCopyFromConfirm()` — updates confirm button count and select-all label
- `selectAllCopyFrom()` — toggles all checkboxes
- `executeCopyFrom()` — creates new entries (new UUIDs, current date, blank times), saves, closes modal

### Split Entry
- **⑃ Split** button on any saved row → `openSplit(id)`
- Opens a wide modal with an editable table pre-seeded from the source entry
- Remaining time indicator: Original − Used = Remaining (turns red if over, green if balanced)
- "Add Row" appends a new row with start pre-filled from previous row's finish
- Cascade button available (same logic as edit modal cascade)
- On save: source entry is deleted, split rows replace it

### Invoicing (contractor.html only)

**Create Invoice flow:**
1. Select client (autocomplete from existing entries), enter hourly rate (defaults to company settings), optionally filter by date range
2. Click "Load Uninvoiced Entries" → checklist of all `invoiced: false` entries for that client
3. Select/deselect entries; live totals bar shows subtotal / tax / total
4. Fill invoice date, due date, tax rate, notes → "Create Invoice"
5. On create: new invoice object saved to `data-contractor-invoices.json`; all selected entries marked `invoiced: true`; day view updates to show ✓

**Invoice detail modal** (`#invoiceModal`):
- Opened by "View" button in invoice list → `openInvoiceDetail(id)`
- Shows invoice number, client, dates, entry table with per-row amounts, totals
- Status dropdown: draft → sent → paid → void
- "Save Status" — updates status only
- "Void & Unmark Entries" — sets `status: 'void'`, reverts all `entryIds` entries to `invoiced: false`
- "Print / PDF" → `printInvoice(id)` — injects HTML into `#invoicePrintFrame`, calls `window.print()`

**Read-only invoiced icon in Day/Week views:**
- Replaced checkbox with `<span class="invoiced-icon is-invoiced|not-invoiced">✓|✗</span>`
- `toggleInvoiced()` removed; "✓ Invoice day" / "○ Clear day" / "✓ Invoice week" / "○ Clear week" topbar buttons removed
- `invoiced` field is now written only by `createInvoice()` and `voidInvoice()`

**Key invoice functions:**
| Function | Description |
|---|---|
| `initInvoices()` | Fetch from `/api/contractor/invoices`, fall back to localStorage |
| `nextInvoiceNumber()` | Scans `_invoices` max number, returns `INV-000N` |
| `loadInvoiceableEntries()` | Filters entries by client + not invoiced + date range |
| `updateInvoiceTotals()` | Live subtotal/tax/total in summary bar |
| `createInvoice()` | Validates, builds invoice, saves, marks entries invoiced, re-renders |
| `openInvoiceDetail(id)` | Populates and shows invoice modal |
| `voidInvoice(id)` | Sets status void, unmarks entries, saves both, re-renders |
| `printInvoice(id)` | Builds print HTML into `#invoicePrintFrame`, calls `window.print()` |
| `getClientDetails(clientName)` | Returns client object `{ legalName, address, phone, email, … }` from `_clients` — contractor only |
| `saveClientDetails()` | Reads Client Details form, updates `_clients`, persists — contractor only |

**Amount calculation:** `minutesToDecimal(timeToMinutes(e.duration)) × rate` (rounds to nearest 0.25h)

**Company Settings:**
- Stored in localStorage key `timetracker_company_settings`
- Fields: name, email, address, phone, logo (base64), defaultRate, defaultTaxRate
- Edited via the "Company & Invoicing Defaults" card in the Settings tab
- Logo: file input → `FileReader.readAsDataURL()` → preview + stored in settings
- Applied by `applyCompanySettings()` button; auto-loaded on startup via `populateCompanySettings()`

**Client Details:**
- Stored server-side via `/api/contractor/clients` → `data-contractor-clients.json`; falls back to localStorage key `timetracker_clients_v2`
- Edited via the "Client Details" card in the Settings tab: select a client, fill legal name/address/phone/email, click "Save Client"
- Used by `printInvoice()` to populate the "Bill To" block — falls back gracefully to the raw client name if details are missing
- `populateClientSelect()` is called by `populateSettingsTab()` to keep the dropdown in sync with `_clients`

**Print to PDF:**
- `@media print` CSS hides everything except `#invoicePrintFrame`
- Print frame layout: top bar (company logo + name left, invoice number/dates right), address row (From block left, Bill To block right), line items table, totals right-aligned, notes footer
- "Bill To" block uses `getClientDetails(inv.client)` — shows `legalName` if set, falls back to raw client name; address/phone/email shown if present
- Font: Montserrat (loaded via Google Fonts import in `common.css`)
- `window.print()` → browser's native print dialog → "Save as PDF"


- Sortable columns: Project, Sub Project, Start
- 3-state cycle: asc → desc → default (chronological)
- Sort resets on date change

### Theme
- Dark/light toggle (☀ ⬤ ☾) left of the pipe separator in header
- Defaults to OS `prefers-color-scheme` on first load
- Manual toggle saved to `localStorage` key `timetracker_theme`
- Applied via `data-theme="dark"` on `<html>` — set by inline `<script>` in `<head>` before paint to avoid flash

### 12/24h Clock Format
- Toggle (24H ⬤ 12H) in header right side
- Affects: Start/Finish display columns in day and week views, running clock
- Does NOT affect: inputs (controlled by OS locale), Acc Time, Duration (these are durations not times)
- Saved to `localStorage` key `timetracker_12h`

### Running Clock
- Live `HH:MM:SS` in header, updates every second
- Respects 12/24h toggle: `8:44:16 AM` or `08:44:16`

### Server Status Dot
- Green: saving to `data.json`
- Amber: server unreachable, saving to localStorage

### Keyboard Shortcuts

A global `keydown` listener (`handleGlobalKey`) handles all shortcuts. Global shortcuts are suppressed when any `input`, `select`, or `textarea` is focused — except modal Enter/Escape which always fire.

**Global shortcuts** (disabled while typing in any input):

| Key(s) | Action |
|---|---|
| `←` / `[` | Previous day (`changeDate(-1)`) |
| `→` / `]` | Next day (`changeDate(1)`) |
| `T` | Go to today (`goToday()`) |
| `1` / `2` / `3` / `4` | Switch to Day / Week / Replicon / Settings tab |
| `5` | Switch to Invoicing tab (contractor only) |
| `N` | Focus new-entry row project field |
| `Ctrl+Z` | Undo last delete (only if `_deletedEntry` is set) |
| `?` | Toggle keyboard shortcuts help overlay |

**Modal shortcuts** (always active when a modal is open):

| Key | Action |
|---|---|
| `Escape` | Close active modal |
| `Enter` | Save/confirm — edit modal always; copy-from only if ≥1 entry checked; split modal only if focus is not on a table `input`/`select` |

**New-entry row:**
- `Escape` in any new-row field blurs it, returning focus to the page so global shortcuts work again.

**Focus management:**
- `openEdit()` focuses `#mProject` on open
- `openSplit()` focuses first row's `.sr-project` on open
- `openCopyFrom()` focuses `#copyFromDate` on open

**Focus trap:**
- `trapFocus(modalEl)` is called once per modal at init. Tab from the last focusable element wraps to the first; Shift+Tab from the first wraps to the last. Applied to `editModal`, `splitModal`, `copyFromModal`, `shortcutsModal`, `invoiceModal` (contractor only).

**Shortcuts help overlay:**
- `<div id="shortcutsModal">` — styled like other modals, lists all shortcuts in a table
- Toggled by `toggleShortcutsHelp()` — called by `?` key or the `?` button in the header
- Closed by Escape or clicking the overlay backdrop

**Key functions:**

| Function | Description |
|---|---|
| `handleGlobalKey(e)` | Main keydown router — modal shortcuts + global shortcuts |
| `toggleShortcutsHelp()` | Toggles `shortcutsModal` show class |
| `trapFocus(modalEl)` | Wires Tab/Shift+Tab focus wrap for a modal element |

---

## CSS Architecture

All CSS is in a single `<style>` block in `<head>`. Uses CSS custom properties (`--var`) defined in `:root` for light mode and overridden in `[data-theme="dark"]`.

### Key CSS Variables
```css
--bg, --surface, --surface2   /* backgrounds, light to dark */
--border                       /* borders */
--text, --text-muted, --text-faint  /* text hierarchy */
--accent                       /* header bg, active tab, primary buttons — NOTE: in dark mode this is near-black (#1a1c24), NOT a usable interactive colour */
--accent-interactive           /* dark mode only: #5b6af5 purple — use this for hover/focus colours in dark mode overrides */
--jira-bg, --jira-border, --jira-text  /* Jira warning colors */
--logged-bg, --logged-border   /* logged/success colors */
--mono, --sans                 /* font families */
```

### Important CSS Rules
- `.table-wrap` has `overflow: visible` (not `hidden`) — required for autocomplete dropdowns to escape the table bounds
- Header corner rounding: done via `thead th:first-child` / `thead th:last-child` border-radius since overflow is visible
- New entry row: `.new-row td` — slightly darker background, no border
- `pointer-events: none` on toast when hidden, `pointer-events: all` when `.show` — required for Undo button to be clickable
- **Dark mode hover colours** — never use `var(--accent)` for interactive hover states in dark mode; it resolves to near-black. Always add a `[data-theme="dark"]` override using `#5b6af5` (same as `--accent-interactive`)
- **Native input icons** (calendar, clock) — styled via `::-webkit-calendar-picker-indicator`. In dark mode use `filter: invert(1)` to make them visible. `cursor: pointer` must also be set on this pseudo-element separately.
- `.copy-toast.warn` — amber variant (`#b45309` light / `#d97706` dark) used for warning toasts (e.g. credentials not configured); stays visible 4s instead of 2s
- `.rpb-item`, `.rpb-item:hover`, `.rpb-item-active`, `.rpb-task` — Replicon project browser styles in the Settings tab
- `.replicon-status.ok` / `.replicon-status.err` — per-row submit result indicators in the Replicon tab

---

## Python Server (server.py)

~550 lines. Extends `SimpleHTTPRequestHandler`. `load_data(filename)` and `_handle_post(filename)` are generic — adding a new data file requires only 2 lines in `do_GET` and 2 lines in `do_POST`.

`load_data(filename, default=None)` accepts an optional `default` argument — returns `[]` when omitted (entries/invoices), or the supplied value (e.g. `{}` for clients). This avoids returning an array when the data file is expected to be an object.

`data.json` path is always relative to `server.py`'s own directory (`os.path.dirname(__file__)`).
Port is `5000` — defined as `PORT = 5000`. If changed, also update `const API` in `index.html`.

`is_wsl()` checks `/proc/version` for `"microsoft"` — skips `webbrowser.open()` in WSL to avoid xdg-open errors.

Replicon-specific helpers (`replicon_call`, `queue_requests`, `extract_tasks_from_root`, `_expire_replicon_credentials`, QueueRequests call structure, column formula): [`docs/replicon-api.md`](docs/replicon-api.md).

---

## LocalStorage Keys

| Key | Value | Purpose |
|---|---|---|
| `timetracker_entries_v1` | JSON array | Fallback entry storage when server is down |
| `timetracker_theme` | `'dark'` or `'light'` | Manual theme override |
| `timetracker_12h` | `'true'` or `'false'` | Clock format preference |
| `timetracker_jira_pattern` | regex string | Jira ticket detection pattern (default `PROJ-\d+`) |
| `timetracker_contractor_invoices_v1` | JSON array | Fallback invoice storage when server is down (contractor only) |
| `timetracker_company_settings` | JSON object | Company name/address/phone/email/logo/defaultRate/defaultTaxRate (contractor only) |
| `timetracker_clients_v2` | JSON object | Fallback client details storage when server is down (contractor only); keyed by client name |

---

## Versioning

Version is hardcoded in the variant HTML files' header. In `replicon.html` and `contractor.html` search for the version `<span>` — it appears once per file. Bump both when shipping a shared change; bump only the relevant file for variant-specific changes.

Semantic versioning (major.minor.patch). Full version history and backlog: [`docs/history.md`](docs/history.md).

**Version history:**
- `v1.0.0` — Day view, inline entry row, Jira detection, Replicon tab, Data tab
- `v1.1.0` — Week view (Sat–Fri), Python server backend, heartbeat, localStorage fallback
- `v1.2.0` — Sort, duplicate row, copy as text, undo delete, Enter-to-save, running clock, 12/24h toggle, dark mode, gap/overlap detection
- `v1.3.0` — Time offset cascade feature
- `v1.4.0` — (version in use when v1.4.x patches began)
- `v1.4.1` — Replicon per-row copy now copies comments only
- `v1.4.2` — Dark mode: date-nav chevrons and today button now use `--accent-interactive` on hover
- `v1.4.3` — Dark mode: calendar/clock input icons brightened via `filter: invert(1)`; added `cursor: pointer` on picker indicators
- `v1.4.4` — Autocomplete arrow key navigation (↑/↓/Enter/Escape)
- `v1.4.5` — Split entry modal (⑃ button on each row)
- `v1.5.0` — Copy from another day: "⎘ Copy from..." button in Day View topbar, date nav with chevrons, entry checklist, blank times on paste
- `v1.6.0` — Data tab renamed to Settings; Configuration card added with configurable Jira ticket pattern
- `v1.6.1` — Bug fixes: gap/overlap skips blank-time entries; new-row start prefill uses chronological order; saveModal rejects finish < start; importData awaits save before render/alert; showToast guards against clobbering the Undo button
- `v1.7.0` — Power-user keyboard shortcuts: global shortcuts (←/→/[/] date nav, T today, 1–4 tab switch, N new entry, Ctrl+Z undo, ? help); modal Enter-to-save and Escape-to-close; focus-on-open for all modals; Tab focus trap in all modals; keyboard shortcuts help overlay (? button in header)
- `v1.7.1` — Autocomplete dropdown flips above the input when there isn't enough space below (dynamic positioning)
- `v2.0.0` — Architecture split: shared code extracted to `common.js` + `common.css`; launcher `index.html` with Replicon/Contractor choice; separate data files per variant (`data-replicon.json`, `data-contractor.json`); contractor synced to feature parity with replicon (Copy From modal, configurable Jira pattern, keyboard shortcuts, v1.6.1 bug fixes)
- `v2.1.0` — Invoicing module (contractor only): 5th Invoicing tab; invoice creation from uninvoiced entries with client + date range filter; live totals with tax; invoice persistence to `data-contractor-invoices.json`; invoice list with status badges; invoice detail modal with status management; Void & Unmark entries; Print/PDF via `window.print()`; company settings form (name, address, logo) in Settings tab; read-only invoiced ✓/✗ icon in Day/Week views (replaces checkbox); keyboard shortcut `5` for Invoicing tab
- `v2.2.0` — Client Details (contractor only): per-client billing info (legal name, address, phone, email) stored in `data-contractor-clients.json` via new `/api/contractor/clients` endpoint; Client Details card in Settings tab; "Bill To" block on printed invoices now populated from client details; invoice print layout redesigned (Montserrat font, two-column address block, cleaner line-items table); client data schema upgraded from `[tasks]` array to `{ id, tasks, legalName, address, phone, email }` object with `_backfillClientIds()` migration; `load_data()` now accepts a `default` parameter

---

Replicon API integration detail (credential files, PROJ mode, QueueRequests, row map, submit flow): [`docs/replicon-api.md`](docs/replicon-api.md).

---

## Known Constraints & Decisions

- **No frameworks** — vanilla JS only. No React, Vue, jQuery.
- **No bundler** — single file, everything inline.
- **`type="time"` inputs** render in OS locale format (12h on Windows en-CA). Data is always stored 24h internally. Do not fight this.
- **Acc Time is a duration** (e.g. `2:45`), not a clock time. Never apply `formatTime()` to it.
- **`overflow: visible` on `.table-wrap`** — do not change to `hidden`, it breaks autocomplete dropdowns.
- **`saveEntries()` is async** — all callers must be `async` and use `await saveEntries(...)`.
- **`saveInvoices()` is async** — same pattern as `saveEntries()`; always await it.
- **`saveClients()` is async** — same pattern; always await it.
- **`invoiced` field is write-protected** — only `createInvoice()` and `voidInvoice()` should set it. Never toggle it from the day view or new-row code.
- **Client data is keyed by name** — `_clients` is a plain object `{ [clientName]: { id, tasks, legalName, … } }`, not an array. Never assume it's an array.
- **`timetracker_clients_v1`** (old localStorage key) is superseded by `timetracker_clients_v2` due to the schema change from `[tasks]` to `{ tasks, legalName, … }`. Do not read from v1.
- **`switchTab()` uses `data-tab` attribute** — contractor's tab buttons must have `data-tab="day|week|replicon|data|invoicing"`. Falls back to index for replicon.html (which has no `data-tab` attrs).
- **Gap/overlap** is computed from chronological order, not display sort order.
- **Week definition:** Saturday to Friday (not Mon–Sun).
- **`extract_tasks_from_root` — leaf-only rule** — only append tasks with no `ChildTasks`. Category/folder nodes may be disabled but still have enabled children; always recurse into them regardless of `Enabled`. Removing the `continue` on disabled nodes was a past bug fix — do not re-add it.
- **Row Map bookmarklet href must be set in HTML** — setting it via JS after page load means dragging captures an empty href. The `javascript:` URL must be hardcoded in the `href` attribute at parse time.
- **`SetComment` col is int, `SetDuration` col is str** — the QueueRequests API is inconsistent: `SetDuration` paramList passes col as a string, `SetComment` passes it as an int. Do not normalise them.
- **Credentials are never returned raw** — `GET /api/replicon/credentials` returns `cookie_set: bool` instead of the actual cookie value. The cookie input field is therefore never auto-populated from the server.

---


## How to Make Changes (Legacy — `TimeTrackerSystem/`)

1. Edit the relevant file directly (`replicon.html`, `contractor.html`, `common.js`, `common.css`, `server.py`) — there is no build step
2. Refresh the browser (no build step needed)
3. For server changes, edit `server.py` and restart via `TimeTracker.bat`
4. Bump the version string in the header when done
5. Test: add an entry, edit it, delete + undo, check Replicon tab, check week view

---

## New Stack (v3.x — Multi-User Web App)

> **Status:** In development on the `make-it-real` branch. `TimeTrackerSystem/` remains the production system until feature parity is confirmed.

### Architecture

| Layer | Technology | Hosting |
|---|---|---|
| Backend API | Laravel 12 + Sanctum (Bearer tokens) + Postgres 16 | Fly.io (`yyz`) |
| Database | Postgres 16 | Supabase (pooler port 6543) |
| Frontend SPA | Nuxt 4 (`ssr:false`) + Vue 3 + Vuetify 3 + Pinia + TypeScript | Cloudflare Pages |
| File storage | Supabase Storage (S3-compatible) | Supabase |
| Email (dev) | Mailpit | Docker |
| Email (prod) | Resend or Postmark SMTP | — |
| PDF generation | Spatie Browsershot (Chromium in backend container) | Fly.io |

### Repo layout

```
backend/                       Laravel 11 API
frontend/                      Nuxt 3 SPA
docker/dev/docker-compose.yml  postgres + mailpit + laravel (dev)
.github/workflows/             deploy-backend.yml + deploy-frontend.yml
TimeTrackerSystem/             legacy single-user app (archived after parity)
docs/                          replicon-api.md, history.md, running.md
```

### Dev setup

```bash
# Start backend + postgres + mailpit
docker compose -f docker/dev/docker-compose.yml up -d

# Frontend (run on host for fast HMR)
cd frontend && npm install && npm run dev    # port 3000
```

Backend serves on port 8000. Mailpit UI on port 8025.

**First-time setup:**
```bash
# Run migrations (once postgres container is running)
docker compose -f docker/dev/docker-compose.yml exec laravel php artisan migrate
```

### Backend structure (`backend/`)

```
app/
  Console/Commands/
    ImportTimeTrackerData.php    # timetracker:import {jsonDir} {userId} [--dry-run]
    PurgeExpiredRepliconCredentials.php  # replicon:purge-expired (scheduled every minute)
  Enums/InvoiceStatus.php
  Http/Controllers/Api/
    Auth/{Register,Login,Logout,VerifyEmail,ResendVerification,
          ForgotPassword,ResetPassword}Controller.php
    Replicon/{Entries,Credentials,ProjectsCache,RowMap,Sync,Submit}Controller.php
    Contractor/{Entries,Clients,Invoices,Company}Controller.php
  Http/Resources/               (JSON API shape for each entity)
  Jobs/SyncRepliconProjects.php
  Models/
    User.php
    RepliconTimeEntry.php  RepliconCredential.php
    RepliconProject.php    RepliconTask.php  RepliconRowMap.php
    ContractorTimeEntry.php  Client.php  ClientTask.php
    Invoice.php  CompanySetting.php
    Concerns/{BelongsToUser,HasUuidV7,HasTimeWindow,HasDuration}.php
  Services/
    Replicon/{RepliconClient,RepliconSyncService,RepliconSubmitService}.php
    Invoices/InvoicePdfService.php
database/migrations/            all domain tables
resources/views/invoices/invoice.blade.php
routes/api.php
```

**Key design decisions:**
- `BelongsToUser` trait: boots a global scope scoping every query to `auth()->id()`; auto-assigns `user_id` on `creating`. No-ops in Artisan/queue contexts without `auth()`.
- `HasUuidV7` trait: uses `symfony/uid` v7 UUIDs (monotonic prefix keeps B-tree indexes hot).
- Replicon credentials encrypted at rest via `'encrypted'` cast (AES-256-CBC via `APP_KEY`). `GET /api/replicon/credentials` never returns the raw cookie — only `cookie_set: bool`.
- `invoice_id` FK lives on `contractor_time_entries`, not as a JSON array on invoices. `Invoice::timeEntries()` is `hasMany`.
- Company settings are **server-side** (not localStorage) in the new stack.

**Replicon API quirks preserved from Python port (do not change):**
- `SetDuration paramList[2]` → `(string)$col`
- `SetComment paramList[2]` → `(int)$col`
- Column formula: `($iso + 1) % 7` where `$iso` = Carbon `dayOfWeekIso` (Mon=1..Sun=7)
- `extractLeafTasks`: always recurse into folder nodes regardless of `Enabled`; only append leaf tasks (no `ChildTasks`)
- Action 11 session redirect: parse `sessionId:'([^']+)'` from response, update creds, retry once
- `last_request_index` incremented inside `DB::transaction + lockForUpdate` to prevent races

### Frontend structure (`frontend/`)

```
nuxt.config.ts                 ssr:false, static preset, Vuetify plugin
app.vue
plugins/vuetify.ts             light/dark themes with primary=#5b6af5 in dark
middleware/auth.global.ts      login → verify-email → app route guard
stores/
  auth.ts                      token + user, persisted to localStorage (tt_auth)
  ui.ts                        theme, use12h, currentDate, sortCol, jiraPattern
  contractor.ts                entries + clients + invoices + company
  replicon.ts                  entries + credentials + projects + rowMap
composables/
  useApi.ts                    $fetch wrapper, attaches Bearer, handles 401
  useTimeFormat.ts             formatTime (12/24h), minutesToHHMM, minutesToDecimal
  useGapOverlap.ts             detectGapsAndOverlaps (same logic as common.js)
  useShortcuts.ts              global keydown: ←/→/[/], T, 1-5, tab switch
components/
  entries/   AutocompleteInput, EntryTable, EntryRowNew, EntryEditDialog,
             CopyFromDayDialog, (GapOverlapRow inline in EntryTable)
  contractor/  CompanySettingsCard, ClientDetailsCard, InvoiceCreateCard,
               InvoiceList, InvoiceDetailDialog
  replicon/    CredentialsCard, ProjectBrowser, RowMapEditor
pages/
  login / register / verify-email / forgot-password / reset-password
  replicon/{day,week,compiled,settings}
  contractor/{day,week,compiled,invoicing,settings}
```

**Key frontend constraints:**
- `useShortcuts()` must be called inside `<script setup>` at the page level (not in layouts) to avoid duplicate listeners.
- `AutocompleteInput` uses `inheritAttrs: false` + `v-bind="$attrs"` so it accepts all `v-text-field` props.
- Cascade in `EntryEditDialog`: delta = new finish − original finish; affects rows where `start >= originalFinish` on same day.
- Acc Time is always computed chronologically, regardless of the current display sort.
- Dark mode primary color for interactive elements: `#5b6af5` (`--accent-interactive` in legacy CSS). Never use `--accent` (`#1a1c24`) for hover/focus in dark mode.

### API routes summary

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout                     [auth]
GET    /api/auth/verify-email/{id}/{hash}   [signed]
POST   /api/auth/resend-verification        [auth]
GET    /api/me                              [auth]

GET|POST|PUT|DELETE  /api/replicon/entries/{id?}    [auth+verified]
GET|PUT|DELETE       /api/replicon/credentials       [auth+verified]
GET                  /api/replicon/projects           [auth+verified]
GET|PUT              /api/replicon/row-map            [auth+verified]
POST                 /api/replicon/sync               [auth+verified]
POST                 /api/replicon/submit             [auth+verified]

GET|POST|PUT|DELETE  /api/contractor/entries/{id?}   [auth+verified]
GET|POST|PUT|DELETE  /api/contractor/clients/{id?}   [auth+verified]
GET|POST|PUT|DELETE  /api/contractor/invoices/{id?}  [auth+verified]
GET                  /api/contractor/invoices/{id}/pdf [auth+verified]
GET|PUT              /api/contractor/company          [auth+verified]

GET /api/health
```

### Data migration

To import your existing local JSON files into a new user account:
```bash
# Inside the laravel container (or with APP_URL pointing at a running server)
php artisan timetracker:import ./TimeTrackerSystem {USER_UUID}
# --dry-run to preview counts without writing
```

Imports: `data-replicon.json` (or `data.json`), `data-contractor.json`, `data-contractor-clients.json`, `data-contractor-invoices.json`, `replicon-credentials.json`, `replicon-projects-cache.json`, `replicon-row-map.json`. Reuses existing UUIDs (Postgres is version-agnostic).

### Production deployment

- **Backend:** `fly deploy` from `backend/` (or via GitHub Actions on push to `main`). Fly app: `timetracker-api`, region `yyz`, scale-to-zero. Release command runs `php artisan migrate --force`.
- **Frontend:** `npm run generate` → static `dist/` → Cloudflare Pages (GitHub auto-deploy on push to `main`).
- **Secrets:** `fly secrets set APP_KEY=... DB_HOST=... DB_PASSWORD=... SUPABASE_S3_KEY=...`
- **Database:** Supabase `ca-central-1`, use pooler port 6543 (not 5432) to avoid connection exhaustion on Fly scale-to-zero.

### How to make changes (new stack)

**Backend:**
1. Edit files in `backend/` — no build step
2. `php artisan serve` (or let Docker do it) — changes hot-reload
3. For new API endpoints: add migration → model → controller → route → resource
4. Run `php artisan route:list` to verify

**Frontend:**
1. Edit files in `frontend/` — Nuxt HMR picks up changes instantly
2. `npm run build` before merging to catch TypeScript errors
3. New pages: create under `pages/`, Nuxt auto-registers them
4. New stores: create under `stores/`, Pinia auto-imports via `@pinia/nuxt`

### Open items (resolve before v3 launch)

- [ ] Pick mail provider: Resend vs Postmark (Mailpit covers dev)
- [ ] Logo upload endpoint (`POST /api/contractor/company/logo`) — `CompanySettingsCard` calls it but backend route not yet added; add `multipart/form-data` handler in `CompanyController`
- [ ] Split entry dialog (`openSplit`) — stub in day.vue pages, port from `contractor.html:openSplit`
- [ ] Replicon PROJ mode (dropdown selects vs free-text inputs) — `replicon.mode` state exists in store, UI not yet wired
- [ ] `useShortcuts` keyboard shortcut `N` (focus new-entry row) — ref to new-row input needed from page level
- [ ] Rate limits: bump `throttle:api` to `throttle:120,1` for the `auth:sanctum,verified` group before going live
- [ ] Supabase RLS: not used (auth is in Laravel policies) — document this so future contributors don't add it accidentally
