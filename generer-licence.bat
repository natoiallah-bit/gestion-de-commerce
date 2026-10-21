@echo off
cd /d "%~dp0"
title Generateur de licences AlgoCommerce
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. & pause & exit /b 1)
"%PHP%" outils\generer-licence.php
echo.
pause
