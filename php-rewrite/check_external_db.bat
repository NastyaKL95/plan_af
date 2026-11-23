@echo off
REM Запуск из корня проекта (рядом с папкой scripts), не зависит от текущей папки в cmd
cd /d "%~dp0"
php scripts\check_external_db.php
echo.
pause
