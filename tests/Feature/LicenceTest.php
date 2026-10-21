<?php

namespace Tests\Feature;

use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use App\Support\Licence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicenceTest extends TestCase
{
    use RefreshDatabase;

    private string $clePrivee = '';

    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        // Paire de clés de test : la vraie clé privée d'ASD n'est jamais dans le projet
        $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($k, $this->clePrivee);
        config(['licence.cle_publique' => openssl_pkey_get_details($k)['key']]);
        Licence::$machineForcee = 'ordinateur-de-la-boutique';
        Licence::oublier();
        $this->gerant = User::factory()->gerant()->create();
    }

    protected function tearDown(): void
    {
        Licence::$machineForcee = null;
        parent::tearDown();
    }

    private function cle(array $modif = []): string
    {
        return Licence::generer($modif + [
            'id' => 'ASD-2026-001', 'boutique' => 'Alimentation La Confiance', 'appareils' => 2,
            'machine' => Licence::codeMachine(), 'emise' => '2026-10-21', 'expire' => null,
        ], $this->clePrivee);
    }

    private function finirEssai(): void
    {
        $this->travel(16)->days();
        Licence::oublier();
    }

    private function vendre()
    {
        $p = Produit::create(['nom' => 'Riz', 'prix_achat' => 300, 'prix_vente' => 500, 'unite' => 'kg', 'seuil_alerte' => 1, 'actif' => true]);
        $p->mouvementer(10, 'entree', $this->gerant->id);

        return $this->actingAs($this->gerant)->post(route('caisse.store'), [
            'lignes' => [['produit_id' => $p->id, 'quantite' => 1]], 'montant_paye' => 500, 'mode_paiement' => 'especes',
        ]);
    }

    public function test_quinze_jours_d_essai_puis_ventes_bloquees(): void
    {
        $this->assertSame('essai', Licence::statut()['etat']);
        $this->assertSame(15, Licence::statut()['jours_restants']);
        $this->vendre()->assertRedirect();
        $this->assertSame(1, Vente::count());

        $this->finirEssai();
        $this->assertSame('essai_termine', Licence::statut()['etat']);
        $this->vendre()->assertSessionHasErrors('licence');
        $this->assertSame(1, Vente::count());

        // Les données restent consultables
        $this->actingAs($this->gerant)->get(route('ventes.index'))->assertOk()->assertSee('Les nouvelles ventes sont bloquées');
    }

    public function test_une_licence_valide_debloque_et_s_affiche(): void
    {
        $this->finirEssai();
        $this->actingAs($this->gerant)->put(route('parametres.licence'), ['cle' => $this->cle()])->assertSessionHas('succes');

        $statut = Licence::statut();
        $this->assertSame('valide', $statut['etat']);
        $this->assertSame(2, $statut['appareils_max']);
        $this->vendre()->assertRedirect();
        $this->assertSame(1, Vente::count());
        $this->actingAs($this->gerant)->get(route('parametres.edit'))->assertOk()
            ->assertSee('Licence active')->assertSee(Licence::codeMachine());
    }

    public function test_cles_refusees(): void
    {
        $bonne = $this->cle();
        [$prefixe, $donnees, $signature] = explode('.', $bonne);
        $json = json_decode(base64_decode(strtr($donnees, '-_', '+/')), true);
        $json['appareils'] = 99;
        $falsifiee = $prefixe.'.'.rtrim(strtr(base64_encode(json_encode($json)), '+/', '-_'), '=').'.'.$signature;

        $this->assertNull(Licence::lire($falsifiee));
        $this->assertNull(Licence::lire('n importe quoi'));
        $this->actingAs($this->gerant)->put(route('parametres.licence'), ['cle' => $falsifiee])->assertSessionHasErrors('cle');

        // Clé faite avec une autre clé privée (pas celle d'ASD)
        $autre = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($autre, $autrePrivee);
        $this->assertNull(Licence::lire(Licence::generer(['boutique' => 'X', 'appareils' => 50, 'machine' => '*'], $autrePrivee)));

        // Clé copiée sur un autre ordinateur
        Licence::enregistrer($bonne);
        Licence::$machineForcee = 'un-autre-ordinateur';
        Licence::oublier();
        $this->assertSame('autre_machine', Licence::statut()['etat']);

        // Clé à durée limitée expirée
        Licence::$machineForcee = 'ordinateur-de-la-boutique';
        Licence::enregistrer($this->cle(['expire' => now()->subDay()->toDateString()]));
        $this->finirEssai();
        $this->assertSame('expiree', Licence::statut()['etat']);
        $this->assertFalse(Licence::statut()['ventes_autorisees']);
    }

    public function test_le_nombre_de_telephones_est_limite_par_la_licence(): void
    {
        Licence::enregistrer($this->cle(['appareils' => 2]));
        $connecter = fn (string $uuid) => $this->postJson('/api/connexion', [
            'email' => $this->gerant->email, 'password' => 'password', 'appareil_uuid' => $uuid, 'appareil_nom' => 'Tél',
        ]);

        $tel1 = (string) Str::uuid();
        $connecter($tel1)->assertOk();
        $connecter((string) Str::uuid())->assertOk();
        $connecter((string) Str::uuid())->assertStatus(403)->assertJsonFragment(['message' => "Nombre maximum d'appareils atteint (2). Déconnectez un appareil dans le menu Appareils du site, ou ajoutez des téléphones à votre licence : ".config('licence.contact')]);

        // Un téléphone déjà enregistré peut se reconnecter
        $connecter($tel1)->assertOk();
    }

    public function test_la_synchronisation_donne_le_statut_et_garde_les_ventes_hors_ligne(): void
    {
        $p = Produit::create(['nom' => 'Eau', 'prix_achat' => 200, 'prix_vente' => 400, 'unite' => 'p', 'seuil_alerte' => 1, 'actif' => true]);
        $jeton = $this->postJson('/api/connexion', [
            'email' => $this->gerant->email, 'password' => 'password', 'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'Tél',
        ])->json('jeton');

        $this->finirEssai();
        $this->app['auth']->forgetGuards();
        $r = $this->withToken($jeton)->postJson('/api/synchroniser', ['depuis' => 0, 'changements' => ['ventes' => [[
            'uuid' => (string) Str::uuid(), 'montant_paye' => 400,
            'lignes' => [['produit_uuid' => $p->uuid, 'quantite' => 1, 'prix_unitaire' => 400]],
        ]]]])->assertOk()->json();

        // Une vente faite hors ligne pendant l'essai n'est jamais perdue
        $this->assertSame([], $r['rejets']);
        $this->assertSame(1, Vente::count());
        $this->assertFalse($r['licence']['ventes_autorisees']);
        $this->assertSame('essai_termine', $r['licence']['etat']);
    }
}
