# Time Tracker

A lightweight, local-first time tracking app for logging daily work tasks and compiling them into a Replicon-ready summary. No cloud, no account, no install — just Python and a browser.

---

## What it does

- **Log tasks** throughout the day with project, description, start/finish times
- **Detect gaps and overlaps** between entries automatically
- **Compile a Replicon summary** — hours grouped by project, comments ready to paste
- **Copy entries from a previous day** to reuse recurring tasks
- **Split or cascade** time entries when your schedule shifts
- Works offline — saves to a local `data.json` file with localStorage fallback

---

## Requirements

- **Python 3** (any recent version)
- A modern browser (Chrome, Edge, Firefox)
- Windows (the launcher is a `.bat` file)

---

## Getting started

1. **Download or clone** this repository
2. **Double-click `TimeTracker.bat`** — it starts the local server and opens the app in your browser
3. Start logging time

That's it. No install, no build step, no dependencies.

> To stop the app, close the terminal window that opened with it.

---

## How to use

### Day View

The main screen. Each row is a time entry.

| Field | Description |
|---|---|
| **Project** | Project code (e.g. `1234`) |
| **Sub Project** | Sub-category (e.g. `General`) |
| **Description** | What you worked on. Use a Jira ticket number (e.g. `PROJ-123`) to enable the logged checkbox |
| **Sub-Description** | Additional detail |
| **Start / Finish** | Time range — type or use the time picker |
| **Duration** | Calculated automatically |
| **Acc Time** | Cumulative time logged for the day |

**Row actions:** Edit (✎) · Duplicate (⧉) · Split (⑃) · Copy as text (⎘) · Delete (✕ with 5s undo)

**Keyboard shortcuts in the new-entry row:**
- `Enter` moves to the next field
- `Enter` on Finish saves the entry
- `↑ / ↓` navigate autocomplete suggestions; `Escape` closes them

---

### Week View

A read-only Sat–Fri overview of all entries, grouped by day.

---

### Replicon Tab

Compiles all entries for the current day into a Replicon-ready table:

- Entries are grouped by **Project + Sub Project**
- Hours are summed and rounded to the nearest **0.25**
- Comments are formatted as `(0.5) Description - SubDescription, ...`

**Copy per row** — copies comments only (paste into Replicon's comments field)  
**Copy All** — copies full rows (project, hours, comments) tab-separated

---

### Settings Tab

- **Configuration** — set the Jira ticket pattern to match your team's project key (e.g. `PROJ-\d+`)
- **Export / Import** — back up and restore your data as JSON or CSV
- **Stats** — total entries, days logged, and more

---

## Features

| Feature | Details |
|---|---|
| **Jira detection** | Entries matching the configured pattern get an amber highlight and a "Needs Jira log" badge; check the box once logged |
| **Gap & overlap detection** | Amber gap indicators and red overlap badges inserted automatically between rows |
| **Time cascade** | When editing a finish time, shift all subsequent entries by the same delta in one click |
| **Split entry** | Divide one entry into multiple with a dedicated modal |
| **Copy from another day** | Copy any entries from a previous date (times are cleared; fields are preserved) |
| **Dark mode** | Follows OS preference; toggle manually in the header |
| **12/24h clock** | Toggle in the header; affects display only, not stored data |
| **Offline fallback** | If the Python server goes down, entries save to localStorage and sync back when the server returns |

---

## Data

All data is stored locally in `TimeTrackerSystem/data.json`. It is never sent anywhere.

The file is excluded from this repository (via `.gitignore`) — your entries stay on your machine.

---

## Project structure

```
TimeTracker.bat              ← Windows launcher
TimeTrackerSystem/
  index.html                 ← Entire app (vanilla HTML/CSS/JS, no build step)
  server.py                  ← Python HTTP server (~70 lines)
  data.json                  ← Your entries (auto-created, gitignored)
```

---

## Tech stack

- **Frontend:** Vanilla HTML, CSS, JavaScript — single file, no frameworks, no bundler
- **Backend:** Python `http.server` — serves the file and exposes a two-endpoint JSON API
- **Storage:** `data.json` (primary) + `localStorage` (fallback)

---

## Version

**v1.6.0** — see [CLAUDE.md](CLAUDE.md) for full version history and developer notes.
