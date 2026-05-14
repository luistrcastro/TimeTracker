# Time Tracker — Running the App

## New stack (v3.x — multi-user web app)

See the [root README](../README.md) for full instructions: local dev setup with Docker, deployment to Fly.io, and migrating data from the legacy format.

---

## Legacy app (TimeTrackerSystem/)

The original single-file Python + vanilla JS app. No install, no build step.

### Windows (native)

1. Double-click `TimeTracker.bat` on the repo root
2. The bat checks Python is installed, `cd`s into `TimeTrackerSystem/`, and runs `server.py`
3. App opens at http://localhost:5000 automatically
4. Close the terminal window to stop

### WSL (Windows Subsystem for Linux)

1. Copy `TimeTracker-WSL.bat` to your Windows Desktop
2. Open it in a text editor and fill in the two variables:
   ```bat
   set WSL_PATH=/home/youruser/path/to/TimeTrackerSystem
   set WSL_DISTRO=Ubuntu    # run: wsl -l  to find your distro name
   ```
3. Double-click it — opens a WSL terminal running `server.py`, then opens the browser from the Windows side

WSL2 proxies `localhost` ports to Windows automatically — no firewall config needed.

`server.py` detects WSL via `/proc/version` and skips its own `webbrowser.open()` call to avoid xdg-open errors.

### Data files

| File | Contents |
|---|---|
| `data-replicon.json` | Replicon variant entries |
| `data-contractor.json` | Contractor variant entries |
| `data-contractor-invoices.json` | Invoices |
| `data-contractor-clients.json` | Client billing details |
| `replicon-credentials.json` | Replicon session credentials (gitignored) |

All files are auto-created on first save and are gitignored.
