#!/usr/bin/env sh
# Installation sur Linux / Mac (sur Windows : installer.bat)
set -e
cd "$(dirname "$0")"
composer install --no-interaction
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=$' .env && php artisan key:generate --force
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force --seed
echo
echo "Compte gérant : gerant@boutique.local / changer-ce-mot-de-passe"
echo "Pour lancer : ./demarrer.sh"
