<?php

/**
 * Générateur de licences AlgoCommerce — réservé à Algorithme Service Digital.
 *
 * Utilise la clé privée rangée dans  Documents\AlgoCommerce-licences\cle-privee-licences.pem
 * (jamais dans le dossier du projet, qui est installé chez les clients).
 * Chaque licence créée est notée dans  Documents\AlgoCommerce-licences\registre-licences.csv
 * et enregistrée dans un fichier texte prêt à envoyer par WhatsApp.
 */
const PREFIXE = 'ALGO1';

function b64(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function demander(string $question, string $defaut = ''): string
{
    echo $question.($defaut !== '' ? " [{$defaut}]" : '').' : ';
    $reponse = trim((string) fgets(STDIN));
    if ($reponse !== '' && function_exists('mb_check_encoding') && ! mb_check_encoding($reponse, 'UTF-8')) {
        $reponse = mb_convert_encoding($reponse, 'UTF-8', 'CP850'); // console Windows
    }

    return $reponse === '' ? $defaut : $reponse;
}

function arreter(string $message): never
{
    echo PHP_EOL.'*** '.$message.PHP_EOL;
    exit(1);
}

$documents = (getenv('USERPROFILE') ?: getenv('HOME')).DIRECTORY_SEPARATOR.'Documents';
$dossier = getenv('ALGO_LICENCES_DOSSIER') ?: $documents.DIRECTORY_SEPARATOR.'AlgoCommerce-licences';
$fichierCle = $dossier.DIRECTORY_SEPARATOR.'cle-privee-licences.pem';

echo PHP_EOL.'=== Générateur de licences AlgoCommerce — Algorithme Service Digital ==='.PHP_EOL.PHP_EOL;

if (! is_file($fichierCle)) {
    arreter("Clé privée introuvable.\n    Copiez le fichier cle-privee-licences.pem dans :\n    {$dossier}\n    (créez le dossier s'il n'existe pas), puis relancez.");
}
$clePrivee = openssl_pkey_get_private((string) file_get_contents($fichierCle));
if (! $clePrivee) {
    arreter('Le fichier cle-privee-licences.pem est abîmé ou ce n\'est pas la bonne clé.');
}

// Vérifie que cette clé privée correspond bien à la clé publique intégrée à l'application
$publique = openssl_pkey_get_details($clePrivee)['key'];
$config = (string) @file_get_contents(dirname(__DIR__).'/config/licence.php');
if (preg_match('/-----BEGIN PUBLIC KEY-----.*?-----END PUBLIC KEY-----/s', $config, $m)
    && preg_replace('/\s+/', '', $m[0]) !== preg_replace('/\s+/', '', $publique)) {
    arreter("Cette clé privée ne correspond pas à la clé publique de l'application : les licences seraient refusées.");
}

$boutique = demander('Nom de la boutique (affiché sur la licence)');
if ($boutique === '') {
    arreter('Le nom de la boutique est obligatoire.');
}
$appareils = (int) demander('Nombre de téléphones / ordinateurs autorisés', '1');
if ($appareils < 1 || $appareils > 100) {
    arreter('Nombre de téléphones invalide.');
}
$machine = strtoupper(str_replace(' ', '', demander('Code machine du client (Paramètres > Licence), ou * pour tout ordinateur')));
if ($machine !== '*' && ! preg_match('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $machine)) {
    arreter('Code machine invalide : il doit ressembler à 7F3A-91C2-0B4E.');
}
$mois = (int) demander('Durée en mois (laisser vide = à vie)', '');
$expire = $mois > 0 ? date('Y-m-d', strtotime("+{$mois} months")) : null;

$registre = $dossier.DIRECTORY_SEPARATOR.'registre-licences.csv';
$numero = is_file($registre) ? max(count(file($registre, FILE_SKIP_EMPTY_LINES)) - 1, 0) + 1 : 1;
$id = sprintf('ASD-%s-%03d', date('Y'), $numero);

$donnees = [
    'id' => $id,
    'boutique' => $boutique,
    'appareils' => $appareils,
    'machine' => $machine,
    'emise' => date('Y-m-d'),
    'expire' => $expire,
];
$partie = b64(json_encode($donnees, JSON_UNESCAPED_UNICODE));
openssl_sign(PREFIXE.'.'.$partie, $signature, $clePrivee, OPENSSL_ALGO_SHA256) || arreter('Signature impossible.');
$cle = PREFIXE.'.'.$partie.'.'.b64($signature);

// Registre et fichier à envoyer au client
if (! is_file($registre)) {
    file_put_contents($registre, "numero;date;boutique;appareils;code_machine;expire\n");
}
file_put_contents($registre, implode(';', [$id, date('Y-m-d'), str_replace(';', ',', $boutique), $appareils, $machine, $expire ?? 'a vie'])."\n", FILE_APPEND);
$nomFichier = $dossier.DIRECTORY_SEPARATOR.'licence-'.$id.'.txt';
file_put_contents($nomFichier, "Licence AlgoCommerce {$id}\nBoutique : {$boutique}\nTéléphones autorisés : {$appareils}\n"
    .'Validité : '.($expire ? 'jusqu\'au '.date('d/m/Y', strtotime($expire)) : 'à vie')."\n\n"
    ."A coller dans AlgoCommerce : Paramètres > Licence > Clé de licence\n\n{$cle}\n");

echo PHP_EOL.'Licence '.$id.' créée pour « '.$boutique.' » — '.$appareils.' appareil(s), '.($expire ? 'jusqu\'au '.date('d/m/Y', strtotime($expire)) : 'à vie').PHP_EOL;
echo PHP_EOL.'----- CLÉ À COLLER CHEZ LE CLIENT -----'.PHP_EOL.$cle.PHP_EOL.'---------------------------------------'.PHP_EOL;
echo PHP_EOL.'Aussi enregistrée dans : '.$nomFichier.PHP_EOL.'Registre : '.$registre.PHP_EOL;
