@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Mise a jour - Gestion Commerce

echo Recuperation de la derniere version...
git pull
if errorlevel 1 goto erreur
call composer install --no-interaction
if errorlevel 1 goto erreur
php artisan migrate --force
if errorlevel 1 goto erreur
echo.
echo Mise a jour terminee. Relancez demarrer.bat
pause
exit /b 0

:erreur
echo La mise a jour a echoue : lisez le message ci-dessus.
pause
exit /b 1
