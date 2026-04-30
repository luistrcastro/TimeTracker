@echo off

:: Set this to the path of the TimeTrackerSystem folder inside your WSL distro
:: Example: /home/youruser/projects/TimeTracker/TimeTrackerSystem
set WSL_PATH=/path/to/TimeTrackerSystem

:: Set this to your WSL distro name (run: wsl -l in cmd to find it)
set WSL_DISTRO=Ubuntu

start "Time Tracker" wsl -d %WSL_DISTRO% -e bash -c "cd %WSL_PATH% && python3 server.py"
timeout /t 2 /nobreak >nul
start http://localhost:5000
