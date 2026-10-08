@echo off
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS erp_local_db;"
"C:\xampp\mysql\bin\mysql.exe" -u root erp_local_db < "%~dp0app\schema.sql"
echo DONE
