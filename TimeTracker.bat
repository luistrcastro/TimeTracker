@echo off
title Time Tracker

echo.
echo  Checking requirements...
echo.

python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo  ----------------------------------------
    echo  Python is not installed on this machine.
    echo  ----------------------------------------
    echo.
    echo  To install Python:
    echo.
    echo    1. Go to https://www.python.org/downloads
    echo    2. Click "Download Python" and run the installer
    echo    3. IMPORTANT: check "Add Python to PATH" during install
    echo    4. Restart this bat file after installing
    echo.
    echo  ----------------------------------------
    pause
    exit /b 1
)

cd /d "%~dp0TimeTrackerSystem"
echo.
echo  Starting Time Tracker...
echo  Close this window to stop.
echo.
python server.py
