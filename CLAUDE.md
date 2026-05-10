# Time Tracker — Claude Development Guide

This document gives a Claude session full context to continue developing or debugging this project without needing to re-explain history.

---

## Project Overview

A lightweight time tracking web app built for **Luis Felipe Castro** to replace an Excel spreadsheet. Developed by Luis Felipe Castro with the assistance of Claude (Anthropic).

**Core concept:** Log work tasks throughout the day with project, description, start/finish times. Compile entries into a Replicon-compatible summary at end of day.

**This is a public repository.** Never commit sensitive or confidential information — no real project codes, client names, internal ticket numbers, credentials, or personal data. Use generic placeholders in examples and defaults.

**Current version:** v2.2.0 (contractor.html only)

---

## File Structure

```
TimeTracker.bat                  ← Windows launcher (double-click to start)
TimeTracker-WSL.bat              ← WSL launcher template (fill in WSL_PATH and WSL_DISTRO)
TimeTrackerSystem/
  index.html                     ← Landing page: 2-button launcher (Replicon / Contractor)
  replicon.html                  ← Replicon variant (~1620 lines, v2.0.0)
  contractor.html                ← Contractor variant (~1775 lines, v2.0.0)
  common.js                      ← Shared JS (~500 lines): data layer, utilities, theme, modals
  common.css                     ← Shared CSS (~22KB): all styles for both variants
  server.py                      ← Python HTTP server (~100 lines)
  data-replicon.json             ← Replicon entries (auto-created on first save)
  data-contractor.json           ← Contractor entries (auto-created on first save)
  data-contractor-invoices.json  ← Contractor invoices (auto-created on first invoice save)
  data-contractor-clients.json   ← Contractor client details (auto-created on first client save)
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

---

## How It Runs

### Windows (native)
1. User double-clicks `TimeTracker.bat` on their Desktop
2. Bat file checks Python is installed, then `cd`s into `TimeTrackerSystem/` and runs `server.py`
3. Python serves `index.html` at `http://localhost:5000` and opens the browser automatically
4. All data reads/writes go through `GET/POST http://localhost:5000/api/replicon/entries` (replicon) or `/api/contractor/entries` (contractor)
5. Closing the terminal window stops the server

### WSL
1. Copy `TimeTracker-WSL.bat` to the Windows Desktop and fill in `WSL_PATH` and `WSL_DISTRO`
2. Double-clicking it opens a WSL terminal window running `server.py` from the repo path
3. The bat opens the browser from the Windows side after a 2-second delay (WSL2 proxies `localhost` automatically — no firewall config needed)
4. `server.py` detects WSL via `/proc/version` and skips its own `webbrowser.open()` call to avoid xdg-open errors
5. Closing the WSL terminal window stops the server

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
let currentDate      // "YYYY-MM-DD" — shared across all tabs
let _entries         // in-memory cache of all entries
let _usingServer     // bool — true if server is reachable
let _sortCol         // 'project' | 'subProject' | 'start' | null
let _sortDir         // 'asc' | 'desc' | null
let _darkMode        // bool
let _use12h          // bool
let _deletedEntry    // last deleted entry for undo
let _undoTimeout     // timeout handle for undo toast
let _jiraPattern     // regex string, loaded from localStorage key timetracker_jira_pattern
let JIRA_RE          // RegExp built from _jiraPattern
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

---

## Python Server (server.py)

~145 lines. Extends `SimpleHTTPRequestHandler` to serve static files and add generic JSON API endpoints. `load_data(filename)` and `_handle_post(filename)` are generic — adding a new data file requires only 2 lines in `do_GET` and 2 lines in `do_POST`.

Current endpoints:
- `GET/POST /api/entries` → `data.json` (legacy)
- `GET/POST /api/replicon/entries` → `data-replicon.json`
- `GET/POST /api/contractor/entries` → `data-contractor.json`
- `GET/POST /api/contractor/invoices` → `data-contractor-invoices.json`
- `GET/POST /api/contractor/clients` → `data-contractor-clients.json`

`load_data(filename, default=None)` accepts an optional `default` argument — returns `[]` when omitted (entries/invoices), or the supplied value (e.g. `{}` for clients). This avoids returning an array when the data file is expected to be an object.

`data.json` path is always relative to `server.py`'s own directory (`os.path.dirname(__file__)`).
Port is `5000` — defined as `PORT = 5000` at the top of `server.py`. If changed, also update `const API` in `index.html`.

`is_wsl()` checks `/proc/version` for `"microsoft"` — returns `True` when running inside WSL2. Used by `open_browser()` to skip `webbrowser.open()` (which fails in WSL with xdg-open errors). The WSL launcher bat handles browser opening instead.

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

Semantic versioning (major.minor.patch).

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

---

## Planned / Backlog Features

### Phase 2 (remaining)
- Project/sub-project autocomplete improvement (✅ arrow key nav added in v1.4.4; could still improve — e.g. show dropdown on focus, remember frequency)
- Copy entries from another day (✅ done in v1.5.0)
- Filter by project or Jira status
- Hours-per-project summary panel

### Phase 3
- Export to CSV (✅ done in Data tab)
- Replicon-style export per week
- Print-friendly report (✅ invoice print/PDF done in v2.1.0)

### Phase 4
- Multi-user support (shared `data.json` on network drive, entries tagged by user)
- Search across all days
- Monthly summary view
- Jira API integration (push time to tickets via REST API)
- Replicon API integration (pull projects, push time)

---

## How to Make Changes

1. Edit `TimeTrackerSystem/index.html` directly — it's the only file that needs changing for most features
2. Refresh the browser (no build step needed)
3. For server changes, edit `server.py` and restart via `TimeTracker.bat`
4. Bump the version string in the header when done
5. Test: add an entry, edit it, delete + undo, check Replicon tab, check week view
