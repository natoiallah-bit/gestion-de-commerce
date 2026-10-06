@extends('layouts.app')
@section('title', 'Appareils')

@section('content')
<h1>Appareils connectés</h1>
<p class="aide" style="margin-bottom:14px">Téléphones et ordinateurs où l'application est installée. Ils travaillent hors ligne et se synchronisent dès qu'ils ont internet.</p>

<div class="carte tableau">
    <table>
        <thead><tr><th>Code</th><th>Appareil</th><th>Utilisateur</th><th>Dernière synchronisation</th><th class="n">Ventes</th><th></th></tr></thead>
        <tbody>
        @forelse($appareils as $a)
            <tr>
                <td><b>{{ $a->code }}</b></td>
                <td>{{ $a->nom }}</td>
                <td>{{ $a->user->name }}</td>
                <td>
                    {{ $a->derniere_synchro?->diffForHumans() ?? 'jamais' }}
                    @if($a->derniere_synchro && $a->derniere_synchro->lt(now()->subDay()))<span class="badge badge-ambre">plus d'un jour</span>@endif
                </td>
                <td class="n">{{ $a->ventes_count }}</td>
                <td>
                    @if($connectes->has($a->uuid))
                        <form method="POST" action="{{ route('appareils.revoquer', $a) }}" onsubmit="return confirm('Déconnecter cet appareil ? Il devra se reconnecter avec un mot de passe.')">
                            @csrf
                            <button class="btn btn-leger btn-petit">Déconnecter</button>
                        </form>
                    @else
                        <span class="badge badge-gris">Déconnecté</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="vide">Aucun appareil. Installez l'application sur un téléphone ou un ordinateur et connectez-vous.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
