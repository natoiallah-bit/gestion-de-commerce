#!/usr/bin/env bash
# =============================================================================
#  AlgoCommerce : installation sur un serveur en ligne (Ubuntu 22.04 / 24.04)
#  Algorithme Service Digital
#
#  Utilisation (sur le serveur, en une ligne) :
#    curl -fsSL https://raw.githubusercontent.com/natoiallah-bit/gestion-de-commerce/main/outils/installer-serveur.sh \
#      | sudo bash -s -- ADRESSE JETON_DUCKDNS
#
#    ADRESSE        : par exemple algocommerce-td.duckdns.org
#    JETON_DUCKDNS  : le "token" affiché sur duckdns.org (facultatif si l'adresse n'est pas DuckDNS)
#
#  Installe : PHP 8.3, AlgoCommerce, Caddy (https automatique et gratuit),
#  sauvegarde quotidienne de la base, mise à jour de l'adresse DuckDNS.
#  Peut être relancé sans risque : les données existantes sont conservées.
# =============================================================================
set -euo pipefail

ADRESSE="${1:-}"
JETON_DUCKDNS="${2:-}"
DEPOT="${DEPOT:-https://github.com/natoiallah-bit/gestion-de-commerce.git}"
DOSSIER=/var/www/algocommerce
SAUVEGARDES=/var/backups/algocommerce

etape() { echo; echo "=== $* ==="; }
echec() { echo; echo "*** PROBLEME : $*"; exit 1; }

[ "$(id -u)" -eq 0 ] || echec "lancez la commande avec sudo."
[ -n "$ADRESSE" ] || echec "indiquez l'adresse, par exemple : sudo bash installer-serveur.sh algocommerce-td.duckdns.org VOTRE_JETON"
grep -qi ubuntu /etc/os-release || echec "ce script est prévu pour Ubuntu."

export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

# --- 1. Mémoire : les petits serveurs gratuits (1 Go) ont besoin d'un fichier d'échange
etape "1/8 Préparation du serveur"
if [ "$(free -m | awk '/^Mem:/{print $2}')" -lt 2000 ] && ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q /swapfile /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
apt-get update -qq
apt-get install -y -qq git curl unzip ca-certificates gnupg sqlite3 cron debian-keyring debian-archive-keyring apt-transport-https >/dev/null

# --- 2. PHP 8.3 (déjà présent sur Ubuntu 24.04, ajouté via le dépôt ondrej sur 22.04)
etape "2/8 Installation de PHP 8.3"
if ! apt-cache show php8.3-fpm >/dev/null 2>&1; then
    apt-get install -y -qq software-properties-common >/dev/null
    add-apt-repository -y ppa:ondrej/php >/dev/null
    apt-get update -qq
fi
apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl >/dev/null
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null
fi

# --- 3. Caddy : serveur web avec certificat https gratuit et automatique
etape "3/8 Installation de Caddy (https)"
if ! command -v caddy >/dev/null && apt-cache show caddy >/dev/null 2>&1; then
    apt-get install -y -qq caddy >/dev/null || true   # fourni par Ubuntu 24.04
fi
if ! command -v caddy >/dev/null; then
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -qq
    apt-get install -y -qq caddy >/dev/null
fi

# --- 4. Pare-feu du serveur : les images Oracle bloquent les ports 80 et 443 par défaut
etape "4/8 Ouverture des ports 80 et 443"
if command -v iptables >/dev/null; then
    for port in 80 443; do
        iptables -C INPUT -p tcp --dport "$port" -j ACCEPT 2>/dev/null || iptables -I INPUT 5 -p tcp --dport "$port" -j ACCEPT 2>/dev/null || iptables -I INPUT -p tcp --dport "$port" -j ACCEPT
    done
    if command -v netfilter-persistent >/dev/null; then netfilter-persistent save >/dev/null 2>&1 || true; fi
fi
if command -v ufw >/dev/null && ufw status | grep -q active; then ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null; fi

# --- 5. Adresse DuckDNS : pointe vers ce serveur, et se met à jour toutes les 5 minutes
etape "5/8 Adresse ${ADRESSE}"
if [ -n "$JETON_DUCKDNS" ] && [[ "$ADRESSE" == *.duckdns.org ]]; then
    SOUS_DOMAINE="${ADRESSE%.duckdns.org}"
    cat > /usr/local/bin/duckdns-maj <<EOF
#!/bin/sh
curl -fsS "https://www.duckdns.org/update?domains=${SOUS_DOMAINE}&token=${JETON_DUCKDNS}&ip=" >/dev/null
EOF
    chmod 700 /usr/local/bin/duckdns-maj
    /usr/local/bin/duckdns-maj || echo "    (DuckDNS n'a pas répondu, on réessaiera automatiquement)"
    echo "*/5 * * * * root /usr/local/bin/duckdns-maj" > /etc/cron.d/duckdns
fi

# --- 6. AlgoCommerce
etape "6/8 Installation d'AlgoCommerce"
if [ -d "$DOSSIER/.git" ]; then
    git -C "$DOSSIER" pull -q
else
    git clone -q "$DEPOT" "$DOSSIER"
fi
cd "$DOSSIER"
composer install --no-interaction --no-dev --optimize-autoloader --quiet

MOT_DE_PASSE=""
if [ ! -f .env ]; then
    cp .env.example .env
    MOT_DE_PASSE="$(head -c 64 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-12)"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=https://${ADRESSE}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^GERANT_PASSWORD=.*|GERANT_PASSWORD=${MOT_DE_PASSE}|" \
        .env
    grep -q '^SESSION_SECURE_COOKIE=' .env || echo 'SESSION_SECURE_COOKIE=true' >> .env
    php artisan key:generate --force --quiet
fi
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan config:clear --quiet
php artisan migrate --force --seed --quiet
# Le mot de passe n'a servi qu'à créer le compte : on ne le laisse pas dans le fichier
sed -i "s|^GERANT_PASSWORD=.*|GERANT_PASSWORD=|" .env
php artisan config:cache --quiet
php artisan view:cache --quiet
chown -R www-data:www-data storage bootstrap/cache database
chmod 640 .env && chown root:www-data .env

# --- 7. Serveur web
etape "7/8 Configuration du serveur web"
cat > /etc/caddy/Caddyfile <<EOF
${ADRESSE} {
    root * ${DOSSIER}/public
    encode gzip
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
EOF
systemctl enable --now php8.3-fpm >/dev/null 2>&1
systemctl restart php8.3-fpm
systemctl enable caddy >/dev/null 2>&1
systemctl restart caddy

# --- 8. Sauvegarde chaque nuit à 2 h (on garde 30 jours)
etape "8/8 Sauvegarde automatique"
mkdir -p "$SAUVEGARDES" && chmod 700 "$SAUVEGARDES"
cat > /usr/local/bin/algocommerce-sauvegarde <<EOF
#!/bin/sh
umask 077
sqlite3 ${DOSSIER}/database/database.sqlite ".backup '${SAUVEGARDES}/algocommerce-\$(date +%Y-%m-%d).sqlite'"
find ${SAUVEGARDES} -name 'algocommerce-*.sqlite' -mtime +30 -delete
EOF
chmod 700 /usr/local/bin/algocommerce-sauvegarde
echo "0 2 * * * root /usr/local/bin/algocommerce-sauvegarde" > /etc/cron.d/algocommerce-sauvegarde
/usr/local/bin/algocommerce-sauvegarde

echo
echo "================================================================"
echo "  AlgoCommerce est installé !"
echo
echo "  Adresse : https://${ADRESSE}"
echo "  (le certificat https peut prendre 1 à 2 minutes la première fois)"
if [ -n "$MOT_DE_PASSE" ]; then
    echo
    echo "  Compte gérant : gerant@boutique.local"
    echo "  Mot de passe  : ${MOT_DE_PASSE}"
    echo "  NOTEZ-LE MAINTENANT, puis changez-le dans le menu Utilisateurs."
fi
echo
echo "  Dans les applications (téléphone, Windows), adresse du serveur :"
echo "      ${ADRESSE}"
echo "  Sauvegardes : ${SAUVEGARDES} (chaque nuit, 30 jours)"
echo "  Mise à jour : sudo bash ${DOSSIER}/outils/installer-serveur.sh ${ADRESSE}"
echo "================================================================"
