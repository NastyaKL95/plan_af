@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo Очистка файлов в public\uploads ...
php scripts\clear_uploads.php
echo.
echo Дальше импортируйте в MySQL файл database\reset_for_hosting.sql
echo (phpMyAdmin: Импорт, или: mysql -u USER -p DBNAME ^< database\reset_for_hosting.sql)
echo.
pause
