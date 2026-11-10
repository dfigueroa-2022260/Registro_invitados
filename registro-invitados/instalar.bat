@echo off
cd /d "%~dp0"
echo Instalando dependencias (composer)...
call composer install --no-interaction
if errorlevel 1 goto error
echo Creando tablas y cargando datos iniciales...
call php artisan migrate --seed --force
if errorlevel 1 goto error
echo.
echo Listo. Ejecuta iniciar.bat o abre http://registro-invitados.test en Laragon.
pause
exit /b 0
:error
echo.
echo Hubo un error. Revisa el mensaje de arriba (consulta el README).
pause
exit /b 1
