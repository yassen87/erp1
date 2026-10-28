@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
title Hamza for Perfumes POS - Offline

echo ==========================================================
echo        Hamza for Perfumes - Offline POS Desktop
echo ==========================================================
echo.

:: 1. Check if Electron is installed in node_modules
if exist "node_modules\electron\dist\electron.exe" (
    echo Starting Desktop Application via Electron...
    start "" "node_modules\electron\dist\electron.exe" .
    exit /b
)

:: 2. If node is available on the system PATH
where node >nul 2>nul
if %errorlevel% equ 0 (
    if not exist "node_modules" (
        echo First time setup: installing dependencies...
        call npm install
    )
    echo Launching application...
    start "" npm start
    exit /b
)

:: 3. Launch in Dedicated Frameless App Mode (Google Chrome)
set "APP_PATH=%~dp0src\index.html"
set "APP_URL=file:///%APP_PATH:\=/%"

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    echo Starting POS in dedicated application window (Chrome)...
    start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" --app="%APP_URL%" --start-maximized
    exit /b
)

:: 4. Launch in Dedicated Frameless App Mode (Microsoft Edge)
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    echo Starting POS in dedicated application window (Edge)...
    start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" --app="%APP_URL%" --start-maximized
    exit /b
)

:: 5. Default fallback
start "" "%APP_URL%"
exit /b
