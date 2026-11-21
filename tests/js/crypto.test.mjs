import test from 'node:test';
import assert from 'node:assert/strict';
import {
  newLinkSecret, newKdfSalt, parseLinkSecret, keyFromSecret, keyFromSecretAndPassword, encrypt, decrypt,
} from '../../assets-src/js/crypto.js';

const FAST = 1000;

async function seal(text, secret, password, salt) {
  const key = password === null
    ? await keyFromSecret(secret, ['encrypt'])
    : await keyFromSecretAndPassword(secret, password, salt, FAST, ['encrypt']);

  return encrypt(text, key);
}

async function open(box, secret, password, salt) {
  const key = password === null
    ? await keyFromSecret(secret, ['decrypt'])
    : await keyFromSecretAndPassword(secret, password, salt, FAST, ['decrypt']);

  return decrypt(box, key);
}

test('sin contraseña: roundtrip con el secreto como clave', async () => {
  const secret = newLinkSecret();
  const box = await seal('hola', secret, null);
  assert.equal(await open(box, secret, null), 'hola');
});

test('con contraseña: roundtrip', async () => {
  const secret = newLinkSecret();
  const salt = newKdfSalt();
  const box = await seal('texto ñandú 🔐', secret, 'correct horse', salt);
  assert.equal(await open(box, secret, 'correct horse', salt), 'texto ñandú 🔐');
});

test('contraseña incorrecta no descifra', async () => {
  const secret = newLinkSecret();
  const salt = newKdfSalt();
  const box = await seal('x', secret, 'buena', salt);
  await assert.rejects(open(box, secret, 'mala', salt));
});

test('la contraseña correcta con OTRO secreto de enlace no descifra (el secreto es el segundo factor)', async () => {
  const salt = newKdfSalt();
  const box = await seal('x', newLinkSecret(), 'buena', salt);
  await assert.rejects(open(box, newLinkSecret(), 'buena', salt));
});

test('otro salt u otras iteraciones derivan otra clave', async () => {
  const secret = newLinkSecret();
  const box = await seal('x', secret, 'pw', newKdfSalt());
  await assert.rejects(open(box, secret, 'pw', newKdfSalt()));
});

test('la contraseña se normaliza a NFC (é compuesta = e + acento)', async () => {
  const secret = newLinkSecret();
  const salt = newKdfSalt();
  const box = await seal('x', secret, 'caf\u00e9', salt);
  assert.equal(await open(box, secret, 'cafe\u0301', salt), 'x');
});

test('un paste cifrado sin contraseña no se abre con una contraseña', async () => {
  const secret = newLinkSecret();
  const box = await seal('x', secret, null);
  await assert.rejects(open(box, secret, 'pw', newKdfSalt()));
});

test('el secreto del enlace se valida antes de usarlo', () => {
  assert.throws(() => parseLinkSecret(''));
  assert.throws(() => parseLinkSecret('corto'));
  assert.throws(() => parseLinkSecret('!'.repeat(43)));
  assert.equal(parseLinkSecret(newLinkSecret()).length, 32);
});