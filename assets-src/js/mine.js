import { browserKeyStore } from './keystore.js';
import { mountMenu } from './menu.js';

const $ = (id) => document.getElementById(id);
const AJAX = { 'X-Requested-With': 'fetch' };
const PASTE_ID = /^[A-Za-z0-9_-]{22}$/;
const keys = browserKeyStore();
let me = null;
let pastes = [];

const showError = (msg) => {
  $('error').textContent = msg;
  $('error').hidden = false;
};
const showStatus = (msg) => {
  $('status').textContent = msg;
  $('status').hidden = false;
};

async function logout(forget) {
  if (forget) keys.forgetAll();
  try {
    await fetch('/auth/logout', { method: 'POST', headers: AJAX });
  } finally {
    location.href = '/login';
  }
}

function describe(p) {
  const fmt = (iso) => new Date(iso).toLocaleString();
  const reads = p.burnAfterRead ? 'una sola lectura' : p.maxViews ? `${p.views}/${p.maxViews} lecturas` : `${p.views} lecturas`;

  return `Creado el ${fmt(p.createdAt)} · ${p.expiresAt ? `vence el ${fmt(p.expiresAt)}` : 'sin vencimiento'} · ${reads}`;
}

function render() {
  const secrets = keys.get(me.id);
  const list = $('list');
  list.replaceChildren();
  $('empty').hidden = pastes.length > 0;

  for (const p of pastes) {
    if (!PASTE_ID.test(p.id)) continue;

    const row = $('row').content.cloneNode(true);
    row.querySelector('.id').textContent = p.id;
    row.querySelector('.badges').textContent = p.hasPassword ? '🔒 con contraseña (vas a necesitarla para abrirlo)' : '';
    row.querySelector('.meta').textContent = describe(p);

    const secret = secrets[p.id];
    if (secret) {
      const url = `${location.origin}/p/${p.id}#${secret}`;

      const open = row.querySelector('.open');
      open.href = url;
      open.hidden = false;

      const copy = row.querySelector('.copy');
      copy.hidden = false;
      copy.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(url);
          copy.textContent = 'Copiado';
        } catch {
          showError('No pudimos copiar el enlace. Usá «Abrir» y copiá la dirección desde la barra del navegador.');
        }
      });

      const forget = row.querySelector('.forget');
      forget.hidden = false;
      forget.addEventListener('click', () => {
        keys.forget(me.id, p.id);
        render();
      });
    } else {
      row.querySelector('.nokey').hidden = false;
    }

    list.append(row);
  }
}

async function load() {
  const meRes = await fetch('/auth/me', { headers: AJAX, cache: 'no-store' });
  if (meRes.status === 401) {
    location.href = '/login';
    return;
  }
  me = await meRes.json();
  mountMenu(me, { logout: false });
  $('whoami').textContent = `Sesión iniciada como ${me.email}`;

  const res = await fetch('/pastes/mine', { headers: AJAX, cache: 'no-store' });
  if (res.status === 403) {
    showError('Tu cuenta no tiene permiso para ver la lista de pastes.');
    return;
  }
  if (!res.ok) {
    showError('No pudimos cargar la lista. Probá de nuevo en unos minutos.');
    return;
  }

  const data = await res.json();
  pastes = data.pastes;
  $('truncated').hidden = !data.truncated;

  if (!data.truncated) keys.prune(me.id, new Set(pastes.map((p) => p.id)));

  render();
}

$('logout').addEventListener('click', () => logout(false));
$('logout-forget').addEventListener('click', () => logout(true));
$('forget-all').addEventListener('click', () => {
  const n = keys.forgetAll();
  showStatus(n > 0 ? 'Listo, olvidamos las claves de este navegador.' : 'No había claves guardadas en este navegador.');
  render();
});

load().catch(() => showError('No pudimos cargar la lista. Probá de nuevo en unos minutos.'));