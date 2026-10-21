// Vérifie le serveur intégré (build-serveur) avant de publier l'installateur :
// démarrage, page de connexion, connexion d'un téléphone, synchronisation, code machine.
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');
const { demarrer, preparer } = require('../electron/serveur-demarrage.cjs');

const racine = path.join(__dirname, '..', 'build-serveur');
const php = path.join(racine, 'php', process.platform === 'win32' ? 'php.exe' : 'php');
const dossierLaravel = path.join(racine, 'laravel');
const dossierDonnees = path.join(os.tmpdir(), 'algocommerce-verif-' + Date.now());

(async () => {
  const r = await demarrer({ php, dossierLaravel, dossierDonnees, port: 8199 });
  const verifier = async (chemin, attendu) => {
    const res = await fetch(r.url + chemin);
    if (res.status !== attendu) throw new Error(`${chemin} : ${res.status} au lieu de ${attendu}`);
    console.log(`OK ${chemin} ${res.status}`);
  };
  await verifier('/up', 200);
  await verifier('/login', 200);
  await verifier('/api/ping', 200);

  const c = await fetch(r.url + '/api/connexion', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'gerant@boutique.local', password: 'changer-ce-mot-de-passe', appareil_uuid: '6f1c2a52-6e0b-4b1e-9d3f-0a1b2c3d4e5f', appareil_nom: 'Test' }),
  });
  const j = await c.json();
  if (c.status !== 200 || !j.jeton) throw new Error('connexion API : ' + c.status + ' ' + JSON.stringify(j));
  const s = await fetch(r.url + '/api/synchroniser', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + j.jeton },
    body: JSON.stringify({ depuis: 0 }),
  });
  const js = await s.json();
  if (s.status !== 200) throw new Error('synchronisation : ' + s.status);
  console.log('OK synchronisation, licence :', js.licence && js.licence.message);

  const env = preparer({ dossierDonnees, dossierLaravel, port: 8199 });
  const opts = process.platform === 'win32' ? ['-c', path.join(path.dirname(php), 'php.ini'), '-d', `extension_dir=${path.join(path.dirname(php), 'ext')}`] : [];
  const a = spawnSync(php, [...opts, path.join(dossierLaravel, 'artisan'), 'commerce:adresses', '--port=8100'], { env, encoding: 'utf8' });
  console.log(a.stdout || a.stderr);
  if (!/Code de cet ordinateur : [0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}/.test(a.stdout || '')) throw new Error('code machine introuvable');

  if (r.processus) r.processus.kill();
  console.log('Serveur intégré vérifié.');
  process.exit(0);
})().catch((e) => { console.error('ECHEC :', e.message); process.exit(1); });
