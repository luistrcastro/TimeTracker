# Replicon API Integration

All Replicon API calls are proxied through `server.py`. The browser never contacts Replicon directly — avoids CORS and keeps credentials out of the browser.

```
Browser → server.py (proxy) → Replicon QueueRequests API
```

## Credential files (all gitignored)

**`replicon-credentials.json`** — session tokens captured from an active browser session:
```json
{
  "base_url": "https://na9.replicon.com",
  "cookie_header": "...",
  "server_view_state_id": "...",
  "session_id": "..."
}
```
After the 15-minute expiry timer fires, only `base_url` remains; the three session fields are cleared.

**`replicon-projects-cache.json`** — populated by Sync or the Row Map bookmarklet:
```json
{
  "synced_at": "2026-05-07T10:30:00",
  "base_url": "https://na9.replicon.com",
  "projects": [
    {
      "id": "9339",
      "code": "2259",
      "name": "2259 - Project Name - FY26 - ...",
      "tasks": [
        { "id": "178805", "name": "Software Downtime", "path": ["IT Downtime", "Software Downtime"] }
      ]
    }
  ]
}
```

**`replicon-row-map.json`** — maps `"projectId:taskId"` → timesheet row index:
```json
{ "9339:178805": 17 }
```

## PROJ mode vs FREE mode

Toggled by `_repliconMode` (persisted in `localStorage` key `timetracker_replicon_mode`). The toggle is only shown when `_repliconCache.projects` is non-empty.

| | FREE mode | PROJ mode |
|---|---|---|
| Project field | text input + autocomplete | `<select>` with project codes |
| SubProject field | text input + autocomplete | `<select>` with tasks for selected project |
| `project` stored as | display code (e.g. `"2259"`) | numeric Replicon ID (e.g. `"9339"`) |

Key functions:
- `buildProjectOptions()` — builds `<option value="id">CODE — ShortName</option>` sorted by code
- `buildSubProjectOptions(projectId)` — builds task options; detects duplicate leaf names and prefixes ancestor path for disambiguation
- `refreshSubProjectOptions()` — rebuilds `#nr-subproject` options when `#nr-project` changes
- `buildModalProjectControls(projectVal, subProjectVal)` — swaps edit modal fields to selects in PROJ mode
- `projDisplayName(val)` — maps a stored project ID back to its display code for rendering

## Capture credentials (console script)

Stored in a hidden `<textarea id="credScript">`. Patches `XMLHttpRequest.prototype.send`, intercepts the first `QueueRequests` call, extracts `sessionId`, `serverViewStateId`, and the `cookie` header, and POSTs them to `/api/replicon/credentials`. Restores original XHR methods after capture.

## Row Map bookmarklet

Hardcoded as a `javascript:` URL in the `href` of an `<a class="btn">` element in the Settings tab (not set via JS — drag-to-bookmark requires the href to be set at parse time). Scrapes all `<tr rowid="N">` elements, extracts `projectvalue`/`taskvalue` from the inner `<a>`, and POSTs to `/api/replicon/row-map`.

## Submit to Replicon

"⬆ Submit to Replicon" button in the Replicon tab toolbar. Gated on `_repliconCredsOk` — if any of the 4 credential fields is missing, shows an amber warning toast and returns without making any request.

Per-row status column:
- Empty by default
- `✓` green on success
- `✗` red on error (tooltip shows error message)
- `⚠ No mapping` for rows where `project:task` is not in the row map

## Credentials polling

On `DOMContentLoaded`, `refreshRepliconCreds()` is called once then every 60 seconds via `setInterval`. It fetches `/api/replicon/credentials` and updates `_repliconCredsOk`. Also populates the credential input fields in Settings (Base URL, serverViewStateId, sessionId) — the cookie field is never populated (server never returns the raw cookie value).

## QueueRequests call structure

```python
requests = [
  {'requestIndex': 1, 'methodName': 'SetDuration', 'instanceId': 'timesheet',
   'paramList': ['time', str(row_id), str(col), str(hours)]},
  {'requestIndex': 2, 'methodName': 'SetComment',  'instanceId': 'timesheet',
   'paramList': ['time', str(row_id), col, comments]},   # col is int for SetComment
  {'requestIndex': 3, 'methodName': 'Save', 'instanceId': 'timesheet', 'paramList': []},
]
```

**Column formula:** `col = (date.weekday() + 2) % 7` — maps Python weekday (Mon=0) to Replicon column (Sat=0, Sun=1, Mon=2, … Fri=6).

**Row index:** scraped from DOM via the Row Map bookmarklet. `<tr rowid="N">` contains `<a projectvalue="PID" taskvalue="TID">`. The bookmarklet POSTs `{ rows: [{ rowId, projectId, taskId, projectName, taskName }] }` to `/api/replicon/row-map`; server merges into both `replicon-row-map.json` and `replicon-projects-cache.json`.

## server.py helpers

| Function | Description |
|---|---|
| `replicon_call(base_url, path, hdrs, body)` | Low-level HTTP call to Replicon; raises `RuntimeError` on non-200 |
| `queue_requests(base_url, creds, requests)` | Calls `QueueRequests` endpoint with a list of method dicts |
| `get_return_object(resp, request_index)` | Extracts `CommitRequests[0].ReturnObject` for a given `requestIndex` |
| `extract_tasks_from_root(root_task, _ancestors)` | Recursively collects leaf tasks only (no `ChildTasks`), with full ancestor path |
| `_expire_replicon_credentials()` | Timer callback — clears session fields from credentials file, keeps `base_url` |
| `load_config(filename)` / `save_config(data, filename)` | Read/write a JSON config file from `_data_dir` |

**Credentials expiry timer:** `CREDS_TTL = 900` (15 min). Module-level `_creds_expiry_timer` is cancelled and restarted on every `POST /api/replicon/credentials`.
