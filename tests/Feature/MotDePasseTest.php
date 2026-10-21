<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MotDePasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_mot_de_passe_oublie_est_remplace_et_le_compte_reactive(): void
    {
        $gerant = User::factory()->gerant()->create(['email' => 'gerant@boutique.local', 'actif' => false]);
        User::factory()->create();

        $this->artisan('commerce:mot-de-passe')
            ->expectsQuestion('Numéro du compte', '1')
            ->assertSuccessful();
        $this->assertTrue($gerant->fresh()->actif);
        $this->assertFalse(Hash::check('password', $gerant->fresh()->password));

        $this->artisan('commerce:mot-de-passe', ['email' => 'gerant@boutique.local', '--mot-de-passe' => 'nouveau1234'])->assertSuccessful();
        $this->assertTrue(Hash::check('nouveau1234', $gerant->fresh()->password));

        $this->artisan('commerce:mot-de-passe', ['email' => 'inconnu@x.td'])->assertFailed();
    }
}
