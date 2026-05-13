# Time Tracker — Running the App

## Windows (native)
1. User double-clicks `TimeTracker.bat` on their Desktop
2. Bat file checks Python is installed, then `cd`s into `TimeTrackerSystem/` and runs `server.py`
3. Python serves `index.html` at `http://localhost:5000` and opens the browser automatically
4. All data reads/writes go through `GET/POST http://localhost:5000/api/replicon/entries` (replicon) or `/api/contractor/entries` (contractor)
5. Closing the terminal window stops the server

## WSL
1. Copy `TimeTracker-WSL.bat` to the Windows Desktop and fill in `WSL_PATH` and `WSL_DISTRO`
2. Double-clicking it opens a WSL terminal window running `server.py` from the repo path
3. The bat opens the browser from the Windows side after a 2-second delay (WSL2 proxies `localhost` automatically — no firewall config needed)
4. `server.py` detects WSL via `/proc/version` and skips its own `webbrowser.open()` call to avoid xdg-open errors
5. Closing the WSL terminal window stops the server
