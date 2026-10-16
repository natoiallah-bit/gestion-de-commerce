<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Database\Seeder;

/**
 * Données d'exemple pour essayer l'application : php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $gerant = User::where('role', 'gerant')->firstOrFail();

        $boutique = Boutique::courante();
        if ($boutique->nom === 'Ma Boutique') {
            $boutique->update(['nom' => 'Alimentation La Confiance', 'adresse' => 'Grand marché, N\'Djamena', 'telephone' => '66 00 11 22', 'pied_ticket' => 'Merci et à bientôt !']);
        }

        User::firstOrCreate(['email' => 'vendeur@boutique.local'], [
            'name' => 'Vendeur', 'password' => 'vendeur1234', 'role' => 'vendeur', 'actif' => true,
        ]);

        $fournisseur = Fournisseur::firstOrCreate(['nom' => 'Grossiste du grand marché'], ['telephone' => '66 12 34 56']);

        $catalogue = [
            'Boissons' => [['Eau minérale 1,5 L', 300, 500], ['Boisson gazeuse 33 cl', 300, 500], ['Jus de karkanji 50 cl', 200, 350], ['Thé vert 250 g', 600, 900]],
            'Alimentation' => [['Riz 5 kg', 3500, 4250], ['Huile 1 L', 1100, 1400], ['Sucre en morceaux 1 kg', 650, 850], ['Lait en poudre 400 g', 1800, 2300], ['Spaghetti 500 g', 350, 500], ['Tomate concentrée', 150, 250]],
            'Hygiène' => [['Savon de toilette', 250, 400], ['Dentifrice', 500, 750], ['Lessive en poudre', 400, 600]],
        ];

        foreach ($catalogue as $nomCategorie => $produits) {
            $categorie = Categorie::firstOrCreate(['nom' => $nomCategorie]);
            foreach ($produits as [$nom, $achat, $vente]) {
                $produit = Produit::firstOrCreate(['nom' => $nom], [
                    'categorie_id' => $categorie->id, 'prix_achat' => $achat, 'prix_vente' => $vente, 'seuil_alerte' => 5,
                ]);
                if ($produit->wasRecentlyCreated) {
                    $produit->mouvementer(rand(8, 40), 'entree', $gerant->id, [
                        'prix_achat_unitaire' => $achat, 'fournisseur_id' => $fournisseur->id, 'note' => 'Stock de démonstration',
                    ]);
                }
            }
        }

        $awa = Client::firstOrCreate(['nom' => 'Fatimé Hassane'], ['telephone' => '66 45 12 33', 'adresse' => 'Quartier Moursal']);
        Client::firstOrCreate(['nom' => 'Mahamat Abakar'], ['telephone' => '99 21 09 87', 'adresse' => 'Quartier Chagoua']);
        Client::firstOrCreate(['nom' => 'Ngarlem Djimadoum'], ['telephone' => '62 33 45 67']);

        if (Vente::count() === 0) {
            $eau = Produit::where('nom', 'Eau minérale 1,5 L')->first();
            $riz = Produit::where('nom', 'Riz 5 kg')->first();
            Vente::enregistrer([['produit_id' => $eau->id, 'quantite' => 3]], 1500, 'especes', null, $gerant->id);
            Vente::enregistrer([['produit_id' => $riz->id, 'quantite' => 1], ['produit_id' => $eau->id, 'quantite' => 2]], 3000, 'especes', $awa->id, $gerant->id);
        }
    }
}
