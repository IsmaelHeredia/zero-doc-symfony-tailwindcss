import { newLinkSecret, newKdfSalt, keyFromSecret, keyFromSecretAndPassword, encrypt, KDF_ITERATIONS } from './crypto.js';
import { browserKeyStore } from './keystore.js';
import { mountMenu } from './menu.js';

const $ = (id) => document.getElementById(id);
const form = $('paste-form');
const AJAX = { 'X-Requested-With': 'fetch' };
const keys = browserKeyStore();
let me = null;

async function showIdentity() {
  $('whoami').textContent = 'Modo anónimo: los pastes vencen como máximo a los 7 días y el tamaño es reducido.';
  try {
    const res = await fetch('/auth/me', { headers: AJAX, cache: 'no-store' });
    if (res.ok) {
      me = await res.json();
      $('whoami').textContent = `Sesión iniciada como ${me.email}: tus pastes van a quedar asociados a tu cuenta.`;
      $('nav-guest').hidden = true;
      mountMenu(me);
      $('remember-block').hidden = false;
    }
  } catch {
    return;
  }
}
showIdentity();

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  $('error').hidden = true;
  $('stored-note').hidden = true;
  const { text, ttl, views, password, password2, remember } = form.elements;

  try {
    if (password.value !== password2.value) throw new Error('Las contraseñas no coinciden.');
    const wantsRemember = me !== null && remember.checked;

    const secret = newLinkSecret();
    let key;
    let kdf = null;
    if (password.value) {
      const salt = newKdfSalt();
      key = await keyFromSecretAndPassword(secret, password.value, salt, KDF_ITERATIONS, ['encrypt']);
      kdf = { salt, iterations: KDF_ITERATIONS };
    } else {
      key = await keyFromSecret(secret, ['encrypt']);
    }
    const { ciphertext, iv } = await encrypt(text.value, key);

    const res = await fetch('/pastes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...AJAX },
      body: JSON.stringify({
        ciphertext,
        iv,
        kdf,
        ttl: ttl.value === 'never' ? null : Number(ttl.value),
        burnAfterRead: views.value === 'burn',
        maxViews: /^\d+$/.test(views.value) ? Number(views.value) : null,
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? 'No pudimos crear el paste. Probá de nuevo en unos minutos.');

    $('link').value = `${location.origin}/p/${data.id}#${secret}`;
    $('delete-token').value = data.deleteToken ?? '';
    $('token-block').hidden = data.deleteToken === null;
    $('owner-note').hidden = data.deleteToken !== null;
    $('result').hidden = false;

    if (wantsRemember && data.deleteToken === null) {
      const stored = keys.save(me.id, data.id, secret);
      $('stored-note').textContent = stored
        ? 'Guardamos el enlace en este navegador: lo vas a ver en «Mis pastes».'
        : 'No pudimos guardar la clave en este navegador. Copiá el enlace ahora, porque después no vamos a poder recuperarlo.';
      $('stored-note').hidden = false;
    }

    form.reset();
  } catch (err) {
    $('error').textContent = err.message;
    $('error').hidden = false;
  }
});

document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText($(button.dataset.copy).value);
      button.textContent = 'Copiado';
    } catch {
      button.textContent = 'Seleccioná y copiá a mano';
    }
  });
});