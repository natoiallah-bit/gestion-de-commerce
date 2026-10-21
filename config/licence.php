<?php

return [
    // Clé publique d'Algorithme Service Digital : elle vérifie les clés de licence.
    // La clé privée correspondante reste uniquement chez ASD (generer-licence.bat).
    'cle_publique' => env('LICENCE_CLE_PUBLIQUE', <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEgvHBuirn2RR0iZxEY22etXnHJ+QC
PalT8fuLT9CYeDNXDU49rU7R+ynYTh01J85yLbQu7DhDt16oyjRikXVMgQ==
-----END PUBLIC KEY-----
PEM),

    'essai_jours' => 15,

    // Téléphones / ordinateurs connectables pendant l'essai
    'essai_appareils' => 3,

    'contact' => 'Algorithme Service Digital : 66 46 22 57 / 66 49 08 12',
];
