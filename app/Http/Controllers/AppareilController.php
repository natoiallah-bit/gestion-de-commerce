<?php

namespace App\Http\Controllers;

use App\Models\Appareil;
use Laravel\Sanctum\PersonalAccessToken;

class AppareilController extends Controller
{
    public function index()
    {
        $appareils = Appareil::with('user')->withCount('ventes')->orderByDesc('derniere_synchro')->get();
        $connectes = PersonalAccessToken::where('name', 'like', 'appareil:%')->pluck('name')
            ->map(fn ($n) => substr($n, strlen('appareil:')))->flip();

        return view('appareils.index', compact('appareils', 'connectes'));
    }

    // Téléphone perdu ou employé parti : l'appareil devra se reconnecter
    public function revoquer(Appareil $appareil)
    {
        PersonalAccessToken::where('name', 'appareil:'.$appareil->uuid)->delete();

        return back()->with('succes', "L'appareil {$appareil->code} est déconnecté.");
    }
}
