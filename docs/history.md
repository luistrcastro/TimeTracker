# Time Tracker — Version History & Roadmap

## Version History

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
- `v2.2.0` — Replicon API integration (replicon.html only): credential capture via console script; project/task sync via `QueueRequests`; row map bookmarklet (scrapes `rowid` from live timesheet DOM); PROJ mode (select dropdowns backed by cache); one-click Submit to Replicon (SetDuration + SetComment + Save); per-row submit status indicators; credentials expiry timer (15 min, server-side); 60s credentials polling; amber warning toast when submitting without valid credentials

---

## Backlog

- Filter entries by project or Jira status
- Hours-per-project summary panel
- Replicon-style export per week
- Multi-user support (shared `data.json` on network drive, entries tagged by user)
- Search across all days
- Monthly summary view
- Jira API integration (push time to tickets via REST API)
- Autocomplete: show dropdown on focus, remember entry frequency
