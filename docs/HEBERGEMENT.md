# Héberger AlgoCommerce gratuitement (Oracle Cloud + DuckDNS)

Résultat : AlgoCommerce accessible partout sur internet à une adresse du type
**`https://algocommerce-td.duckdns.org`**, sans payer d'hébergement.

Les téléphones se synchronisent alors depuis n'importe où (3G/4G ou Wi-Fi), et le gérant
suit sa boutique depuis chez lui. Le mode hors ligne fonctionne toujours.

Durée : environ 45 minutes la première fois.

---

## Étape 1 — Créer le compte Oracle Cloud (gratuit)

1. Aller sur **https://www.oracle.com/cloud/free/** → **Start for free**.
2. Remplir : pays **Chad**, nom, e-mail, mot de passe.
3. **Home Region** : choisir **France Central (Paris)** ou **France South (Marseille)**.
   ⚠️ Ce choix est définitif, et les serveurs gratuits n'existent que dans cette région.
4. Ajouter la carte bancaire : c'est une **vérification**. Oracle peut bloquer une petite somme qu'il rend
   aussitôt, et **ne prélève rien** tant qu'on reste dans l'offre « Always Free ».
5. Attendre l'e-mail « Your account is ready » (de quelques minutes à quelques heures).

## Étape 2 — Créer l'adresse gratuite DuckDNS

1. Aller sur **https://www.duckdns.org** → se connecter avec un compte **Google**.
2. Dans **sub domain**, taper par exemple `algocommerce-td` → **add domain**.
3. **Noter le `token`** affiché en haut de la page (une longue suite de lettres et de chiffres).
   Ne pas le partager.

## Étape 3 — Créer le serveur

1. Dans Oracle Cloud : menu ☰ → **Compute** → **Instances** → **Create instance**.
2. **Name** : `algocommerce`.
3. **Image and shape** → **Edit** :
   - **Image** : **Canonical Ubuntu 24.04**.
   - **Shape** : **Ampere — VM.Standard.A1.Flex** avec **1 OCPU** et **6 GB** de mémoire
     (étiquette « Always Free-eligible »).
     Si Oracle répond « Out of capacity », choisir **AMD — VM.Standard.E2.1.Micro** (aussi gratuit).
4. **Add SSH keys** : choisir **Generate a key pair for me**, puis cliquer sur
   **Save private key** et **garder ce fichier** (`ssh-key-….key`). Il sert à entrer sur le serveur.
5. **Create**. Après 1 à 2 minutes, l'instance est **Running**.
   **Noter la « Public IP address »** (par exemple `152.67.10.20`).

## Étape 4 — Ouvrir les portes du site (ports 80 et 443)

1. Sur la page de l'instance : cliquer sur le lien du **Subnet** (sous « Primary VNIC »).
2. Onglet **Security** (ou **Security Lists**) → **Default Security List** → **Add Ingress Rules** :
   - **Source CIDR** : `0.0.0.0/0`
   - **IP Protocol** : TCP
   - **Destination Port Range** : `80,443`
   - **Add Ingress Rules**.

## Étape 5 — Faire pointer l'adresse vers le serveur

Sur **duckdns.org**, dans la ligne `algocommerce-td`, mettre la **Public IP** du serveur dans
**current ip** → **update ip**.

## Étape 6 — Installer AlgoCommerce (une seule commande)

1. Dans Oracle Cloud, cliquer sur l'icône **Cloud Shell** (le symbole `>_` en haut à droite).
   Une fenêtre noire s'ouvre en bas de l'écran.
2. Dans le menu ⚙️ de la Cloud Shell → **Upload** → choisir le fichier `ssh-key-….key` de l'étape 3.
3. Taper (en remplaçant `152.67.10.20` par l'IP du serveur) :
   ```
   chmod 600 ssh-key-*.key
   ssh -i ssh-key-*.key ubuntu@152.67.10.20
   ```
   Répondre `yes` à la question. On est maintenant **sur le serveur**.
4. Copier-coller cette commande (en remplaçant l'adresse et le token) :
   ```
   curl -fsSL https://raw.githubusercontent.com/natoiallah-bit/gestion-de-commerce/main/outils/installer-serveur.sh | sudo bash -s -- algocommerce-td.duckdns.org VOTRE_TOKEN_DUCKDNS
   ```
5. Attendre 5 à 10 minutes. À la fin, le script affiche :
   - l'adresse du site ;
   - le **compte gérant** et son **mot de passe** → **les noter immédiatement**.

## Étape 7 — Vérifier

1. Ouvrir **https://algocommerce-td.duckdns.org** dans un navigateur (le cadenas 🔒 doit apparaître).
2. Se connecter avec le compte gérant, puis **changer le mot de passe** (menu Utilisateurs).
3. Sur un téléphone **en 4G (Wi-Fi coupé)** : dans l'application, adresse du serveur
   `algocommerce-td.duckdns.org`, puis e-mail et mot de passe.

---

## Ensuite

- **Mettre à jour AlgoCommerce** : se connecter au serveur (étape 6.3) puis
  `sudo bash /var/www/algocommerce/outils/installer-serveur.sh algocommerce-td.duckdns.org`
- **Sauvegardes** : automatiques chaque nuit à 2 h, gardées 30 jours dans `/var/backups/algocommerce`.
- **Reprendre les données de l'ordinateur de la boutique** : copier le fichier
  `database\database.sqlite` du PC vers `/var/www/algocommerce/database/database.sqlite` sur le serveur
  (on peut le faire ensemble), puis relancer le script de mise à jour.

## Si quelque chose ne marche pas

| Problème | Solution |
|---|---|
| « Out of capacity » à la création | Choisir le shape **VM.Standard.E2.1.Micro**, ou réessayer plus tard |
| Le site ne s'ouvre pas | Vérifier l'étape 4 (ports 80 et 443) et l'étape 5 (IP sur DuckDNS) |
| Pas de cadenas https | Attendre 2 minutes après l'installation, puis recharger la page |
| `Permission denied (publickey)` | Vérifier le nom du fichier de clé et la commande `chmod 600` |
