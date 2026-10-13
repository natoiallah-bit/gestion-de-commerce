@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Installation - Gestion Commerce

where php >nul 2>nul
if errorlevel 1 (
    echo PHP est introuvable. Installez PHP 8.3 ou plus, comme pour la gestion scolaire.
    pause
    exit /b 1
)
where composer >nul 2>nul
if errorlevel 1 (
    echo Composer est introuvable : https://getcomposer.org/download/
    pause
    exit /b 1
)

echo [1/4] Installation des composants...
call composer install --no-interaction
if errorlevel 1 goto erreur

echo [2/4] Configuration...
if not exist .env copy .env.example .env >nul
findstr /r /c:"^APP_KEY=$" .env >nul && php artisan key:generate --force

echo [3/4] Base de donnees...
if not exist database\database.sqlite type nul > database\database.sqlite
php artisan migrate --force --seed
if errorlevel 1 goto erreur

echo [4/4] Termine.
echo.
echo Compte gerant : gerant@boutique.local / changer-ce-mot-de-passe
echo (changez le mot de passe apres la premiere connexion : menu Utilisateurs)
echo.
echo Pour lancer l'application : double-cliquez sur demarrer.bat
pause
exit /b 0

:erreur
echo.
echo L'installation a echoue : lisez le message ci-dessus.
pause
exit /b 1
