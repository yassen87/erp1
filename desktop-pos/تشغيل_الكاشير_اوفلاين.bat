@echo off
chcp 65001 >nul
title حمزة للعطور — برنامج الكاشير (أوفلاين)
cd /d "%~dp0"

echo ======================================================================
echo          حمزة للعطور - برنامج كاشير ونقطة بيع الفروع (أوفلاين)
echo ======================================================================
echo.

:: Check if electron is available in node_modules
if exist "node_modules\electron" (
    echo [1/2] جاري تشغيل التطبيق عبر Electron...
    start "" "node_modules\.bin\electron.cmd" .
    exit /b
)

:: Try running via npx electron if node is installed
where node >nul 2>nul
if %errorlevel% equ 0 (
    if not exist "node_modules" (
        echo [1/2] تثبيت بيئة العمل لأول مرة... يرجى الانتظار ثواني...
        call npm install
    )
    echo [2/2] جاري فتح البرنامج...
    start "" npm start
    exit /b
)

:: Instant Native App Mode Fallback (Works immediately via Microsoft Edge or Chrome)
echo [1/1] جاري تشغيل الكاشير في نافذة مستقلة (Desktop App Mode)...
set "APP_URL=file:///%~dp0src/index.html"
set "APP_URL=%APP_URL:\=/%"

where msedge >nul 2>nul
if %errorlevel% equ 0 (
    start msedge --app="%APP_URL%" --window-size=1366,768 --start-maximized
    exit /b
)

where chrome >nul 2>nul
if %errorlevel% equ 0 (
    start chrome --app="%APP_URL%" --window-size=1366,768 --start-maximized
    exit /b
)

:: Default browser fallback
start "" "%~dp0src\index.html"
exit /b
