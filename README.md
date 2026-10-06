# Gestion de commerce

Gestion pour petits commerces (boutique, alimentation, quincaillerie…), en français, montants en FCFA.

Trois parties :

| Partie | Où | Rôle |
|---|---|---|
| **Serveur** (Laravel, ce dossier) | hébergé en ligne | site web du gérant + API de synchronisation |
| **Application Android** (`GestionCommerce.apk`) | téléphones | caisse **hors ligne**, se synchronise dès qu'il y a internet |
| **Application Windows** (`GestionCommerce-Installation-….exe`) | ordinateurs | même application, installée sur le PC |

Les applications Android et Windows sont construites à partir du dossier [`application/`](application/).

## Télécharger les applications

Onglet **Releases** du dépôt GitHub → dernière version → télécharger :
- `GestionCommerce.apk` sur le téléphone, puis l'ouvrir (autoriser « installer des applications inconnues » si demandé) ;
- `GestionCommerce-Installation-….exe` sur l'ordinateur Windows, puis le lancer.

Au premier lancement : adresse du serveur, e-mail et mot de passe (comptes créés par le gérant
sur le site web, menu Utilisateurs). Ensuite l'application fonctionne **sans internet**.

### Comment marche la synchronisation

- Chaque vente, client, remboursement, produit, entrée de stock ou dépense est enregistré d'abord
  **dans l'appareil**, puis envoyé au serveur dès que la connexion est là (au démarrage, toutes les
  minutes, quelques secondes après une vente, ou avec le bouton « Synchroniser »).
- L'appareil reçoit en retour ce que les autres appareils et le site web ont fait.
- Une coupure pendant l'envoi ne crée jamais de doublon (chaque opération a un identifiant unique).
- Les tickets sont numérotés par appareil (`A1-000012`, `A2-000003`…) pour ne jamais se mélanger.
- Si deux téléphones vendent hors ligne le dernier article, les deux ventes sont gardées et le stock
  passe en négatif : le gérant le voit et corrige par un ajustement.
- Le gérant voit tous les appareils (menu **Appareils**) et peut déconnecter un téléphone perdu.

### Héberger le serveur

N'importe quel hébergement PHP 8.3 avec MySQL ou SQLite convient. Utilisez une adresse **https**
(certificat gratuit Let's Encrypt chez la plupart des hébergeurs) : c'est elle que l'on saisit
dans les applications.

## Fonctionnalités

## Fonctionnalités

| Module | Contenu |
|---|---|
| **Caisse** | Vente rapide par clic ou code-barres, rendu de monnaie, espèces / mobile money, ticket imprimable (80 mm) |
| **Produits & stock** | Catalogue, catégories, prix d'achat et de vente, alerte de stock bas, entrées de marchandise, ajustements d'inventaire, historique de chaque mouvement |
| **Clients & crédits** | Vente à crédit (paiement partiel), dette par client, remboursements, historique |
| **Dépenses** | Loyer, transport, salaires…, fournisseurs |
| **Rapports** | Chiffre d'affaires, marge, bénéfice net, trésorerie, produits les plus vendus, ventes par vendeur |
| **Comptes** | **Gérant** : accès complet. **Vendeur** : caisse, ses ventes, clients |

Règles importantes :
- les prix d'une vente sont toujours lus en base, jamais envoyés par le navigateur ;
- le stock ne bouge que par des mouvements tracés (vente, entrée, ajustement, annulation) ;
- seul le gérant peut annuler une vente : le stock est remis et le crédit du client effacé ;
- un produit déjà vendu n'est jamais supprimé, il est désactivé ;
- les achats de marchandise enregistrés comme dépense ne sont pas déduits du bénéfice
  (leur coût est compté au moment de la vente via le prix d'achat).

## Installation

Prérequis : PHP 8.3+, Composer, SQLite (ou MySQL).

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Le compte gérant créé par défaut est `gerant@boutique.local` / `changer-ce-mot-de-passe`
(modifiable avant l'installation avec `GERANT_EMAIL` et `GERANT_PASSWORD` dans `.env`).
Changez le mot de passe dès la première connexion (Utilisateurs → Modifier).

Pour essayer avec des données d'exemple :

```bash
php artisan db:seed --class=DemoSeeder
```

(ajoute aussi un compte `vendeur@boutique.local` / `vendeur1234`).

## Tests

```bash
php artisan test                 # serveur
cd application && npm test       # logique hors ligne de l'application
```

## Développer l'application

```bash
cd application
npm install
npm run dev        # dans le navigateur
npm run exe        # installateur Windows (sur Windows)
npm run android    # puis ouvrir application/android dans Android Studio
```

GitHub Actions construit automatiquement l'APK et l'EXE à chaque modification du dossier
`application/` et les publie dans Releases sous le numéro de version de `application/package.json`
(augmenter ce numéro, par exemple 1.0.1, pour publier une nouvelle version).
