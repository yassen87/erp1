@echo off
setlocal
cd /d "%~dp0"
title Hamza for Perfumes POS - Install Dependencies

echo ==========================================================
echo        Hamza for Perfumes - Installing Electron POS
echo ==========================================================
echo.
echo Installing npm packages, please wait...
call npm install
echo.
echo ==========================================================
echo Installation complete! You can now run run_pos.bat
echo ==========================================================
pause
