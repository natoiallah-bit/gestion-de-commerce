<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appareil;
use App\Models\User;
use App\Support\Licence;
use App\Support\Synchronisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    // Connexion depuis l'application : un jeton par appareil
    public function connexion(Request $request)
    {
        $donnees = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'appareil_uuid' => 'required|uuid',
            'appareil_nom' => 'required|string|max:100',
        ]);

        $user = User::where('email', $donnees['email'])->first();
        if (! $user || ! $user->actif || ! Hash::check($donnees['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou compte désactivé.']);
        }

        // Nombre d'appareils limité par la licence (un appareil déjà connu peut toujours revenir)
        $licence = Licence::statut();
        if (Appareil::nombreConnectes($donnees['appareil_uuid']) >= $licence['appareils_max']) {
            return response()->json([
                'message' => "Nombre maximum d'appareils atteint ({$licence['appareils_max']}). "
                    .'Déconnectez un appareil dans le menu Appareils du site, ou ajoutez des téléphones à votre licence : '.$licence['contact'],
            ], 403);
        }

        $appareil = Appareil::enregistrer($donnees['appareil_uuid'], $donnees['appareil_nom'], $user);
        $user->tokens()->where('name', 'appareil:'.$appareil->uuid)->delete();
        $jeton = $user->createToken('appareil:'.$appareil->uuid)->plainTextToken;

        return response()->json([
            'jeton' => $jeton,
            'utilisateur' => ['uuid' => $user->uuid, 'nom' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'appareil' => ['uuid' => $appareil->uuid, 'code' => $appareil->code, 'nom' => $appareil->nom],
        ]);
    }

    public function deconnexion(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Une synchronisation complète en un appel :
     * 1. l'appareil envoie ses changements faits hors ligne ;
     * 2. il reçoit en retour tout ce qui a changé sur le serveur depuis sa dernière révision.
     */
    public function synchroniser(Request $request)
    {
        $request->validate([
            'depuis' => 'required|integer|min:0',
            'changements' => 'nullable|array',
        ]);

        $user = $request->user();
        if (! $user->actif) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $appareil = Appareil::where('uuid', substr($user->currentAccessToken()->name, strlen('appareil:')))->first();
        if (! $appareil) {
            return response()->json(['message' => 'Appareil inconnu, reconnectez-vous.'], 401);
        }

        $sync = new Synchronisation($user, $appareil);
        $envoi = $sync->envoyer($request->input('changements', []));
        $reception = $sync->recevoir($request->integer('depuis'));

        return response()->json($envoi + $reception + [
            'utilisateur' => ['uuid' => $user->uuid, 'nom' => $user->name, 'role' => $user->role],
            'heure_serveur' => now()->toIso8601String(),
            'licence' => self::licencePourAppareil(),
        ]);
    }

    private static function licencePourAppareil(): array
    {
        $licence = Licence::statut();

        return [
            'etat' => $licence['etat'],
            'message' => $licence['message'],
            'ventes_autorisees' => $licence['ventes_autorisees'],
            'jours_restants' => $licence['jours_restants'],
            'contact' => $licence['contact'],
        ];
    }
}
