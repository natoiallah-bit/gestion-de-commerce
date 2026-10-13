@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Gestion Commerce - serveur (laisser ouvert)

if not exist .env (
    echo Lancez d'abord installer.bat
    pause
    exit /b 1
)

rem Applique les mises a jour de la base apres une nouvelle version
php artisan migrate --force >nul

php artisan commerce:adresses
echo  Laissez cette fenetre ouverte pendant que la boutique travaille.
echo  Si Windows demande l'autorisation pour PHP, cochez "Reseaux prives" et autorisez.
echo.

start "" http://127.0.0.1:8000
php artisan serve --host=0.0.0.0 --port=8000
pause
