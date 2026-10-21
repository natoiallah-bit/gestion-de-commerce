// AlgoCommerce Serveur : tout compris pour l'ordinateur de la boutique.
// Démarre le serveur (PHP portable + Laravel) puis affiche AlgoCommerce dans une fenêtre.
const { app, BrowserWindow, Menu, dialog, shell } = require('electron');
const path = require('path');
const { demarrer } = require('./serveur-demarrage.cjs');

const ressources = app.isPackaged ? process.resourcesPath : path.join(__dirname, '..', 'build-serveur');
const php = path.join(ressources, 'php', process.platform === 'win32' ? 'php.exe' : 'php');
const dossierLaravel = path.join(ressources, 'laravel');
const dossierDonnees = path.join(app.getPath('userData'), 'donnees');

let serveur = null;
let fenetre = null;
let fermetureConfirmee = false;

const ATTENTE = `data:text/html;charset=utf-8,${encodeURIComponent(`<!doctype html><html><body style="margin:0;height:100vh;display:flex;align-items:center;justify-content:center;background:#0f2a22;color:#fff;font-family:Segoe UI,Arial,sans-serif"><div style="text-align:center"><div style="font-size:26px;font-weight:700">AlgoCommerce</div><div style="color:#e0a030;margin-top:10px">Démarrage en cours…</div><div style="color:#9db5aa;font-size:13px;margin-top:6px">Le premier lancement peut prendre une minute.</div></div></body></html>`)}`;

async function lancer() {
  Menu.setApplicationMenu(null);
  fenetre = new BrowserWindow({
    width: 1280, height: 820, minWidth: 360, title: 'AlgoCommerce',
    icon: path.join(__dirname, '..', 'public', 'icone.png'),
    autoHideMenuBar: true,
    webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: true },
  });
  fenetre.loadURL(ATTENTE);

  // Les liens qui sortent d'AlgoCommerce s'ouvrent dans le navigateur
  fenetre.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:/.test(url) && !url.startsWith('http://127.0.0.1')) shell.openExternal(url);
    return { action: url.startsWith('http://127.0.0.1') ? 'allow' : 'deny' };
  });

  fenetre.on('close', (e) => {
    if (fermetureConfirmee || !serveur) return;
    const choix = dialog.showMessageBoxSync(fenetre, {
      type: 'question', buttons: ['Fermer AlgoCommerce', 'Annuler'], defaultId: 1, cancelId: 1,
      title: 'Fermer AlgoCommerce ?',
      message: 'Fermer AlgoCommerce ?',
      detail: 'Pendant qu\'AlgoCommerce est fermé, les téléphones continuent de vendre mais ne peuvent plus se synchroniser avec cet ordinateur.',
    });
    if (choix === 0) fermetureConfirmee = true; else e.preventDefault();
  });

  try {
    const r = await demarrer({ php, dossierLaravel, dossierDonnees, port: 8100 });
    serveur = r.processus;
    await fenetre.loadURL(r.url);
  } catch (e) {
    dialog.showErrorBox('AlgoCommerce ne peut pas démarrer', `${e.message}\n\nContact : Algorithme Service Digital — 66 46 22 57 / 66 49 08 12`);
    app.quit();
  }
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (fenetre) { if (fenetre.isMinimized()) fenetre.restore(); fenetre.focus(); }
  });
  app.whenReady().then(lancer);
  app.on('window-all-closed', () => app.quit());
  app.on('before-quit', () => { if (serveur) serveur.kill(); });
}
