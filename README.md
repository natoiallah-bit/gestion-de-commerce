# Gestion de commerce

Application web de gestion pour petits commerces (boutique, alimentation, quincaillerie…),
en français, montants en FCFA, utilisable sur ordinateur, tablette et téléphone.

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
php artisan test
```
