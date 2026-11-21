import { parseLinkSecret, keyFromSecret, keyFromSecretAndPassword, decrypt } from './crypto.js';

const $ = (id) => document.getElementById(id);
const id = location.pathname.split('/').pop();
const secret = location.hash.slice(1);

let payload = null;

const showError = (msg) => {
  $('error').textContent = msg;
  $('error').hidden = false;
};

async function init() {
  try {
    parseLinkSecret(secret);
  } catch {
    showError('El enlace no tiene una clave válida: falta o está dañada la parte posterior al «#». Pedí que te lo reenvíen completo.');
    $('reveal').disabled = true;
    return;
  }

  try {
    const res = await fetch(`/pastes/${id}/meta`, { cache: 'no-store' });
    if (res.status === 404) {
      showError('Este paste no existe, ya venció o ya fue leído.');
      $('reveal').disabled = true;
      return;
    }
    if (res.ok) $('password-block').hidden = !(await res.json()).hasPassword;
  } catch {
    return;
  }
}
init();

async function tryDecrypt() {
  try {
    const key = payload.kdf
      ? await keyFromSecretAndPassword(secret, $('password').value, payload.kdf.salt, payload.kdf.iterations, ['decrypt'])
      : await keyFromSecret(secret, ['decrypt']);

    return await decrypt(payload, key);
  } catch {
    return null;
  }
}

$('reveal').addEventListener('click', async () => {
  $('error').hidden = true;
  $('reveal').disabled = true;

  try {
    if (!$('password-block').hidden && !$('password').value) {
      showError('Escribí la contraseña para continuar.');
      return;
    }

    if (payload === null) {
      const res = await fetch(`/pastes/${id}`, { cache: 'no-store' });
      if (!res.ok) {
        showError(res.status === 404 ? 'Este paste no existe, ya venció o ya fue leído.' : 'El servidor tuvo un problema. Probá de nuevo en unos minutos.');
        return;
      }
      payload = await res.json();
    }

    if (payload.kdf && !$('password').value) {
      $('password-block').hidden = false;
      showError('Este paste tiene contraseña: escribila y volvé a tocar el botón.');
      return;
    }

    const text = await tryDecrypt();
    if (text === null) {
      showError(
        payload.kdf
          ? 'La contraseña es incorrecta o el enlace está dañado. Podés volver a intentar sin gastar otra lectura, siempre que no recargues la página.'
          : 'No pudimos descifrar el contenido: la clave no corresponde a este paste.',
      );
      return;
    }

    $('content').textContent = text;
    $('content').hidden = false;
    $('reveal').hidden = true;
    $('password').value = '';
    $('password-block').hidden = true;
  } finally {
    $('reveal').disabled = false;
  }
});

$('delete').addEventListener('click', async () => {
  $('error').hidden = true;

  const headers = { 'X-Requested-With': 'fetch' };
  const token = $('token').value.trim();
  if (token) headers['X-Delete-Token'] = token;

  const res = await fetch(`/pastes/${id}`, { method: 'DELETE', headers });
  if (res.status === 204) {
    $('content').hidden = true;
    showError('El paste fue borrado.');
  } else if (res.status === 403) {
    showError('No tenés autorización para borrarlo: usá el token de borrado o ingresá con una cuenta que tenga el permiso.');
  } else {
    showError('No pudimos borrarlo. Es posible que ya no exista.');
  }
});

$('report').addEventListener('click', async () => {
  const status = $('report-status');
  status.hidden = true;

  const res = await fetch('/reports', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
    body: JSON.stringify({
      pasteId: id,
      reason: $('report-reason').value,
      comment: $('report-comment').value.trim() || null,
    }),
  });
  const data = await res.json().catch(() => ({}));

  status.textContent =
    res.status === 202 ? 'Gracias, recibimos tu reporte.'
    : res.status === 429 ? 'Enviaste demasiados reportes. Probá de nuevo más tarde.'
    : (data.message ?? 'No pudimos enviar el reporte.');
  status.hidden = false;
  if (res.status === 202) $('report-comment').value = '';
});