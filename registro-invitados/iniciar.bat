@echo off
cd /d "%~dp0"
echo App en http://localhost:8000  (Ctrl+C para detener)
call php artisan serve --host=0.0.0.0 --port=8000
