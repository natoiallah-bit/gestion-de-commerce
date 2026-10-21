<?php

use App\Models\User;
use App\Support\AdresseReseau;
use App\Support\Licence;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

// Affiche l'adresse à taper dans les applications (téléphone, ordinateur)
Artisan::command('commerce:adresses {--port=8100}', function () {
    $port = (int) $this->option('port');
    $urls = AdresseReseau::urls($port);

    $this->newLine();
    $this->line('  Sur CET ordinateur (navigateur ou application Windows) :');
    $this->info("      http://127.0.0.1:{$port}");
    $this->newLine();
    if ($urls) {
        $this->line('  Sur les TÉLÉPHONES connectés au même Wi-Fi, adresse du serveur :');
        foreach ($urls as $url) {
            $this->info("      {$url}");
        }
    } else {
        $this->warn('  Aucun réseau détecté : connectez cet ordinateur au Wi-Fi de la boutique.');
    }
    $this->newLine();
    $licence = Licence::statut();
    $this->line('  Licence : '.$licence['message']);
    $this->line('  Code de cet ordinateur : '.Licence::codeMachine());
    $this->newLine();
})->purpose("Affiche l'adresse du serveur pour les applications");

// Mot de passe oublié : remet un nouveau mot de passe à un compte (reinitialiser-mot-de-passe.bat)
Artisan::command('commerce:mot-de-passe {email? : e-mail du compte} {--mot-de-passe= : nouveau mot de passe (sinon généré)}', function () {
    $users = User::orderByRaw("role = 'gerant' desc")->orderBy('name')->get();
    if ($users->isEmpty()) {
        $this->error('Aucun compte : lancez d\'abord installer.bat');

        return 1;
    }

    $email = $this->argument('email');
    if (! $email) {
        $this->newLine();
        $this->line('  Comptes existants :');
        foreach ($users as $i => $u) {
            $this->line(sprintf('    %d. %s  —  %s (%s)%s', $i + 1, $u->email, $u->name, $u->libelleRole(), $u->actif ? '' : ' [désactivé]'));
        }
        $choix = trim((string) $this->ask('Numéro du compte', '1'));
        $user = $users->get(((int) $choix) - 1);
    } else {
        $user = $users->firstWhere('email', $email);
    }
    if (! $user) {
        $this->error('Compte introuvable.');

        return 1;
    }

    $motDePasse = $this->option('mot-de-passe') ?: Str::password(10, symbols: false);
    $user->update(['password' => $motDePasse, 'actif' => true]);

    $this->newLine();
    $this->info('  Nouveau mot de passe pour '.$user->email.' : '.$motDePasse);
    $this->line('  Notez-le, puis changez-le après connexion (menu Utilisateurs).');
    $this->newLine();

    return 0;
})->purpose('Remet un nouveau mot de passe à un compte');
