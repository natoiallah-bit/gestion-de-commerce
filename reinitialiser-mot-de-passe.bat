@echo off
cd /d "%~dp0"
title Mot de passe oublie - AlgoCommerce
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. & pause & exit /b 1)
if not exist vendor\autoload.php (echo Lancez d'abord installer.bat & pause & exit /b 1)
"%PHP%" artisan commerce:mot-de-passe
echo.
pause
