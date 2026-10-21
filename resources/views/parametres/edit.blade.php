@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')
<h1>Paramètres de la boutique</h1>

<form class="carte formulaire" method="POST" action="{{ route('parametres.update') }}">
    @csrf @method('PUT')
    <label for="nom">Nom de la boutique</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $boutique->nom) }}" required maxlength="120">
    <label for="adresse">Adresse</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $boutique->adresse) }}" maxlength="255">
    <label for="telephone">Téléphone</label>
    <input type="text" id="telephone" name="telephone" value="{{ old('telephone', $boutique->telephone) }}" maxlength="60">
    <label for="pied_ticket">Message en bas du ticket</label>
    <input type="text" id="pied_ticket" name="pied_ticket" value="{{ old('pied_ticket', $boutique->pied_ticket) }}" maxlength="255" placeholder="Merci de votre visite !">
    <button class="btn btn-ambre" style="margin-top:16px">Enregistrer</button>
</form>

<h2 style="margin-top:24px">Licence AlgoCommerce</h2>
<div class="carte formulaire">
    @php $couleur = $licence['etat'] === 'valide' ? 'badge-vert' : ($licence['ventes_autorisees'] ? 'badge-ambre' : 'badge-rouge'); @endphp
    <p><span class="badge {{ $couleur }}">{{ $licence['etat'] === 'valide' ? 'Licence active' : ($licence['etat'] === 'essai' ? 'Essai' : 'Ventes bloquées') }}</span>
        {{ $licence['message'] }}</p>
    @if($licence['donnees'])
        <p class="aide" style="margin-top:6px">N° {{ $licence['donnees']['id'] ?? '—' }} ·
            {{ ($licence['donnees']['expire'] ?? null) ? 'jusqu\'au '.date('d/m/Y', strtotime($licence['donnees']['expire'])) : 'à vie' }}</p>
    @endif
    <p style="margin-top:8px">Téléphones / ordinateurs connectés : <b>{{ $appareilsConnectes }}</b> sur <b>{{ $licence['appareils_max'] }}</b> autorisés.</p>

    <div class="carte" style="background:var(--fond); margin-top:12px">
        <div class="aide">Code de cet ordinateur (à donner à Algorithme Service Digital pour obtenir la licence) :</div>
        <div style="font-size:22px; font-weight:700; letter-spacing:2px; margin-top:4px">{{ $codeMachine }}</div>
        <div class="aide" style="margin-top:4px">{{ $licence['contact'] }}</div>
    </div>

    <form method="POST" action="{{ route('parametres.licence') }}">
        @csrf @method('PUT')
        <label for="cle">Clé de licence</label>
        <textarea id="cle" name="cle" rows="4" required placeholder="ALGO1.…" style="font-family:monospace; font-size:12px">{{ old('cle') }}</textarea>
        <button class="btn" style="margin-top:12px">Activer la licence</button>
    </form>
</div>
@endsection
