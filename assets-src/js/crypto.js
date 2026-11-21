const encoder = new TextEncoder();
const decoder = new TextDecoder();

export const KDF_ITERATIONS = 600_000;
const SECRET_BYTES = 32;
const SALT_BYTES = 16;

export function b64uEncode(bytes) {
  let bin = '';
  for (const b of bytes) bin += String.fromCharCode(b);
  return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function b64uDecode(str) {
  if (!/^[A-Za-z0-9_-]*$/.test(str)) throw new Error('base64url inválido');
  const b64 = str.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (str.length % 4)) % 4);
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

export function newLinkSecret() {
  return b64uEncode(crypto.getRandomValues(new Uint8Array(SECRET_BYTES)));
}

export function newKdfSalt() {
  return b64uEncode(crypto.getRandomValues(new Uint8Array(SALT_BYTES)));
}

export function parseLinkSecret(secret) {
  const bytes = b64uDecode(secret);
  if (bytes.length !== SECRET_BYTES) throw new Error('secreto de enlace inválido');
  return bytes;
}

export function keyFromSecret(secret, usages) {
  return crypto.subtle.importKey('raw', parseLinkSecret(secret), 'AES-GCM', false, usages);
}

export async function keyFromSecretAndPassword(secret, password, kdfSalt, iterations, usages) {
  const secretBytes = parseLinkSecret(secret);
  const salt = b64uDecode(kdfSalt);
  const fullSalt = new Uint8Array(salt.length + secretBytes.length);
  fullSalt.set(salt, 0);
  fullSalt.set(secretBytes, salt.length);

  const material = await crypto.subtle.importKey('raw', encoder.encode(password.normalize('NFC')), 'PBKDF2', false, ['deriveKey']);

  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', hash: 'SHA-256', salt: fullSalt, iterations },
    material,
    { name: 'AES-GCM', length: 256 },
    false,
    usages,
  );
}

export async function encrypt(plaintext, key) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, encoder.encode(plaintext)));
  return { ciphertext: b64uEncode(ct), iv: b64uEncode(iv) };
}

export async function decrypt({ ciphertext, iv }, key) {
  const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: b64uDecode(iv) }, key, b64uDecode(ciphertext));
  return decoder.decode(plain);
}