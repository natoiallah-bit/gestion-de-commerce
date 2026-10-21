<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Licence AlgoCommerce.
 *
 * Une clé de licence = "ALGO1." + données (base64url JSON) + "." + signature (base64url),
 * signée par Algorithme Service Digital avec sa clé privée. L'application ne connaît que
 * la clé publique : elle peut vérifier une clé, pas en fabriquer.
 *
 * Données : id, boutique, appareils (téléphones autorisés), machine (code machine ou "*"),
 * emise (date), expire (date ou null = à vie).
 */
class Licence
{
    public const PREFIXE = 'ALGO1';

    // Pour les tests : remplace l'identifiant de l'ordinateur
    public static ?string $machineForcee = null;

    // ---------------------------------------------------------------- Ordinateur

    public static function identifiantBrut(): string
    {
        if (static::$machineForcee !== null) {
            return static::$machineForcee;
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $sortie = (string) @shell_exec('reg query "HKLM\SOFTWARE\Microsoft\Cryptography" /v MachineGuid /reg:64 2>nul');
            if (preg_match('/MachineGuid\s+REG_SZ\s+(\S+)/i', $sortie, $m)) {
                return $m[1];
            }
        }
        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $fichier) {
            if (is_readable($fichier) && trim((string) file_get_contents($fichier)) !== '') {
                return trim((string) file_get_contents($fichier));
            }
        }
        if (PHP_OS_FAMILY === 'Darwin' && function_exists('shell_exec')) {
            $sortie = (string) @shell_exec('ioreg -rd1 -c IOPlatformExpertDevice');
            if (preg_match('/"IOPlatformUUID"\s*=\s*"([^"]+)"/', $sortie, $m)) {
                return $m[1];
            }
        }

        return php_uname('n');
    }

    // Code court à transmettre à ASD, ex. 7F3A-91C2-0B4E
    public static function codeMachine(): string
    {
        $h = strtoupper(substr(hash('sha256', 'algocommerce|'.static::identifiantBrut()), 0, 12));

        return implode('-', str_split($h, 4));
    }

    // ---------------------------------------------------------------- Clés

    public static function generer(array $donnees, string $clePrivee): string
    {
        $json = json_encode($donnees, JSON_UNESCAPED_UNICODE);
        $partie = self::b64($json);
        if (! openssl_sign(self::PREFIXE.'.'.$partie, $signature, $clePrivee, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signature impossible : clé privée invalide.');
        }

        return self::PREFIXE.'.'.$partie.'.'.self::b64($signature);
    }

    /**
     * Données de la licence si la clé est authentique, null sinon.
     */
    public static function lire(?string $cle): ?array
    {
        $cle = preg_replace('/\s+/', '', (string) $cle);
        $morceaux = explode('.', $cle);
        if (count($morceaux) !== 3 || $morceaux[0] !== self::PREFIXE) {
            return null;
        }
        [$prefixe, $partie, $signature] = $morceaux;

        $ok = openssl_verify($prefixe.'.'.$partie, self::deb64($signature), config('licence.cle_publique'), OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            return null;
        }
        $donnees = json_decode(self::deb64($partie), true);

        return is_array($donnees) && isset($donnees['boutique'], $donnees['appareils']) ? $donnees : null;
    }

    // ---------------------------------------------------------------- Statut

    public static function ligne(): object
    {
        $ligne = DB::table('licence')->first();
        if (! $ligne) {
            DB::table('licence')->insert(['essai_debut' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $ligne = DB::table('licence')->first();
        }

        return $ligne;
    }

    /**
     * etat : valide | essai | essai_termine | invalide | autre_machine | expiree
     */
    public static function statut(): array
    {
        if (app()->bound('licence.statut')) {
            return app('licence.statut');
        }

        $ligne = self::ligne();
        $contact = config('licence.contact');
        $base = ['donnees' => null, 'jours_restants' => null, 'contact' => $contact];

        if ($ligne->cle) {
            $donnees = self::lire($ligne->cle);
            if (! $donnees) {
                $etat = ['etat' => 'invalide', 'message' => 'La clé de licence enregistrée n\'est pas valide.'];
            } elseif (($donnees['machine'] ?? '*') !== '*' && $donnees['machine'] !== self::codeMachine()) {
                $etat = ['etat' => 'autre_machine', 'message' => 'Cette licence a été délivrée pour un autre ordinateur.'];
            } elseif (! empty($donnees['expire']) && now()->toDateString() > $donnees['expire']) {
                $etat = ['etat' => 'expiree', 'message' => 'La licence a expiré le '.date('d/m/Y', strtotime($donnees['expire'])).'.'];
            } else {
                $etat = ['etat' => 'valide', 'message' => 'Licence valide pour « '.$donnees['boutique'].' ».'];
            }
            $etat['donnees'] = $donnees;
        }

        if (! isset($etat) || $etat['etat'] !== 'valide') {
            $fin = Carbon::parse($ligne->essai_debut)->startOfDay()->addDays((int) config('licence.essai_jours'));
            $jours = (int) now()->startOfDay()->diffInDays($fin, false);
            if (! isset($etat) && $jours > 0) {
                $etat = ['etat' => 'essai', 'message' => "Version d'essai : il reste {$jours} jour".($jours > 1 ? 's' : '').'.', 'jours_restants' => $jours];
            } elseif (! isset($etat)) {
                $etat = ['etat' => 'essai_termine', 'message' => 'La période d\'essai est terminée.'];
            }
        }

        $statut = $etat + $base;
        $statut['ventes_autorisees'] = in_array($statut['etat'], ['valide', 'essai'], true);
        $statut['appareils_max'] = $statut['etat'] === 'valide'
            ? (int) $statut['donnees']['appareils']
            : (int) config('licence.essai_appareils');

        app()->instance('licence.statut', $statut);

        return $statut;
    }

    public static function enregistrer(string $cle): array
    {
        $cle = preg_replace('/\s+/', '', $cle);
        self::ligne();
        DB::table('licence')->update(['cle' => $cle, 'updated_at' => now()]);
        self::oublier();

        return self::statut();
    }

    public static function oublier(): void
    {
        app()->forgetInstance('licence.statut');
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function deb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
