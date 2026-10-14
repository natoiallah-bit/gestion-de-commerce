#!/usr/bin/env sh
# Lance le serveur sur Linux / Mac (sur Windows : demarrer.bat)
cd "$(dirname "$0")"
php artisan migrate --force >/dev/null
php artisan commerce:adresses --port=8100
echo "  Laissez ce terminal ouvert pendant que la boutique travaille."
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8100 --no-reload
