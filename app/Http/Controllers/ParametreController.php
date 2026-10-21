<?php

namespace App\Http\Controllers;

use App\Models\Appareil;
use App\Models\Boutique;
use App\Support\Licence;
use Illuminate\Http\Request;

class ParametreController extends Controller
{
    public function edit()
    {
        return view('parametres.edit', [
            'boutique' => Boutique::courante(),
            'licence' => Licence::statut(),
            'codeMachine' => Licence::codeMachine(),
            'appareilsConnectes' => Appareil::nombreConnectes(),
        ]);
    }

    public function update(Request $request)
    {
        Boutique::courante()->update($request->validate([
            'nom' => 'required|string|max:120',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:60',
            'pied_ticket' => 'nullable|string|max:255',
        ]));

        return back()->with('succes', 'Paramètres enregistrés.');
    }

    public function licence(Request $request)
    {
        $cle = $request->validate(['cle' => 'required|string|max:4000'])['cle'];

        if (! Licence::lire($cle)) {
            return back()->withErrors(['cle' => 'Cette clé de licence n\'est pas valide. Vérifiez qu\'elle a été copiée en entier.'])->withInput();
        }
        $statut = Licence::enregistrer($cle);

        return $statut['etat'] === 'valide'
            ? back()->with('succes', 'Licence activée : '.$statut['message'])
            : back()->withErrors(['cle' => $statut['message']]);
    }
}
