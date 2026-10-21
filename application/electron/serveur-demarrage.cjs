// Démarrage du serveur AlgoCommerce intégré (PHP portable + Laravel), sans Electron :
// utilisable par electron/serveur.cjs et par les tests.
const { spawn, spawnSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const http = require('http');
const net = require('net');
const path = require('path');

function journal(dossierDonnees, texte) {
  try {
    fs.appendFileSync(path.join(dossierDonnees, 'demarrage.log'), `[${new Date().toISOString()}] ${texte}\n`);
  } catch { /* le journal ne doit jamais empêcher le démarrage */ }
}

// Dossiers et réglages propres à cet ordinateur (créés au premier lancement)
function preparer({ dossierDonnees, dossierLaravel, port }) {
  for (const d of ['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'cache']) {
    fs.mkdirSync(path.join(dossierDonnees, d), { recursive: true });
  }
  const fichierReglages = path.join(dossierDonnees, 'reglages.json');
  let reglages = {};
  try { reglages = JSON.parse(fs.readFileSync(fichierReglages, 'utf8')); } catch { /* premier lancement */ }
  if (!reglages.app_key) {
    reglages.app_key = 'base64:' + crypto.randomBytes(32).toString('base64');
    fs.writeFileSync(fichierReglages, JSON.stringify(reglages, null, 2));
  }
  const base = path.join(dossierDonnees, 'database.sqlite');
  if (!fs.existsSync(base)) fs.writeFileSync(base, '');

  const cache = (f) => path.join(dossierDonnees, 'cache', f);
  return {
    ...process.env,
    APP_NAME: 'AlgoCommerce',
    APP_ENV: 'production',
    APP_DEBUG: 'false',
    APP_KEY: reglages.app_key,
    APP_URL: `http://127.0.0.1:${port}`,
    APP_LOCALE: 'fr',
    APP_FALLBACK_LOCALE: 'fr',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: base,
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'warning',
    ALGO_DONNEES: dossierDonnees,
    APP_SERVICES_CACHE: cache('services.php'),
    APP_PACKAGES_CACHE: cache('packages.php'),
    APP_CONFIG_CACHE: cache('config.php'),
    APP_ROUTES_CACHE: cache('routes-v7.php'),
    APP_EVENTS_CACHE: cache('events.php'),
  };
}

function optionsPhp({ php }) {
  // PHP portable de Windows : on indique où sont ses extensions et son php.ini
  if (process.platform !== 'win32') return [];
  const dossier = path.dirname(php);
  return ['-c', path.join(dossier, 'php.ini'), '-d', `extension_dir=${path.join(dossier, 'ext')}`];
}

function portLibre(port) {
  return new Promise((resolve) => {
    const s = net.createServer().once('error', () => resolve(false)).once('listening', () => s.close(() => resolve(true)));
    s.listen(port, '0.0.0.0');
  });
}

function attendre(url, delaiMs) {
  const fin = Date.now() + delaiMs;
  return new Promise((resolve, reject) => {
    const essai = () => {
      http.get(url, (r) => { r.resume(); r.statusCode < 500 ? resolve() : retenter(); }).on('error', retenter);
    };
    const retenter = () => (Date.now() > fin ? reject(new Error('Le serveur ne répond pas.')) : setTimeout(essai, 300));
    essai();
  });
}

async function demarrer({ php, dossierLaravel, dossierDonnees, port = 8100 }) {
  fs.mkdirSync(dossierDonnees, { recursive: true });
  journal(dossierDonnees, `Démarrage (php=${php}, laravel=${dossierLaravel})`);

  if (!(await portLibre(port))) {
    // Peut-être AlgoCommerce déjà ouvert : s'il répond, on l'utilise
    try {
      await attendre(`http://127.0.0.1:${port}/up`, 1500);
      return { processus: null, url: `http://127.0.0.1:${port}`, dejaOuvert: true };
    } catch {
      throw new Error(`Le port ${port} est déjà utilisé par un autre programme. Fermez-le puis relancez AlgoCommerce.`);
    }
  }

  const env = preparer({ dossierDonnees, dossierLaravel, port });
  const artisan = path.join(dossierLaravel, 'artisan');

  // Création / mise à jour de la base (sans rien effacer)
  const migration = spawnSync(php, [...optionsPhp({ php }), artisan, 'migrate', '--force', '--seed'], {
    cwd: dossierLaravel, env, windowsHide: true, encoding: 'utf8', timeout: 120000,
  });
  journal(dossierDonnees, `migrate: code ${migration.status} ${migration.error || ''}\n${migration.stdout || ''}${migration.stderr || ''}`);
  if (migration.status !== 0) {
    throw new Error('Préparation de la base de données impossible. Détails : ' + path.join(dossierDonnees, 'demarrage.log'));
  }

  const publicDir = path.join(dossierLaravel, 'public');
  const routeur = path.join(dossierLaravel, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php');
  const sortie = fs.openSync(path.join(dossierDonnees, 'serveur.log'), 'a');
  const processus = spawn(php, [...optionsPhp({ php }), '-S', `0.0.0.0:${port}`, '-t', publicDir, routeur], {
    cwd: publicDir, env, windowsHide: true, stdio: ['ignore', sortie, sortie],
  });
  processus.on('exit', (code) => journal(dossierDonnees, `Serveur arrêté (code ${code})`));

  try {
    await attendre(`http://127.0.0.1:${port}/up`, 20000);
  } catch (e) {
    processus.kill();
    throw new Error('Le serveur AlgoCommerce n\'a pas démarré. Détails : ' + path.join(dossierDonnees, 'serveur.log'));
  }
  journal(dossierDonnees, 'Serveur prêt');

  return { processus, url: `http://127.0.0.1:${port}`, dejaOuvert: false };
}

module.exports = { demarrer, preparer, portLibre };
