@echo off
chcp 65001 >nul
title حمزة للعطور - تشغيل كاشير سطح المكتب (أوفلاين)
cd /d "%~dp0desktop-pos"
call run_pos.bat
