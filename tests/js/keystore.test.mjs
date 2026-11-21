import test from 'node:test';
import assert from 'node:assert/strict';
import { createKeyStore } from '../../assets-src/js/keystore.js';

class FakeStorage {
  #m = new Map();
  get length() { return this.#m.size; }
  key(i) { return [...this.#m.keys()][i] ?? null; }
  getItem(k) { return this.#m.has(k) ? this.#m.get(k) : null; }
  setItem(k, v) { this.#m.set(k, String(v)); }
  removeItem(k) { this.#m.delete(k); }
}

const USER_A = '11111111-1111-4111-8111-111111111111';
const USER_B = '22222222-2222-4222-8222-222222222222';
const id = (c) => c.repeat(22);
const secret = (c) => c.repeat(43);

test('guardar y leer, separados por cuenta', () => {
  const ks = createKeyStore(new FakeStorage());
  assert.equal(ks.save(USER_A, id('a'), secret('x')), true);
  assert.deepEqual(ks.get(USER_A), { [id('a')]: secret('x') });
  assert.deepEqual(ks.get(USER_B), {});
});

test('rechaza formatos inválidos sin tocar el almacenamiento', () => {
  const storage = new FakeStorage();
  const ks = createKeyStore(storage);
  assert.equal(ks.save(USER_A, 'corto', secret('x')), false);
  assert.equal(ks.save(USER_A, id('a'), 'corto'), false);
  assert.equal(ks.save('no-es-uuid', id('a'), secret('x')), false);
  assert.equal(storage.length, 0);
});

test('datos corruptos o manipulados se sanean', () => {
  const storage = new FakeStorage();
  const ks = createKeyStore(storage);

  storage.setItem(`zk:links:${USER_A}`, '{no es json');
  assert.deepEqual(ks.get(USER_A), {});

  storage.setItem(`zk:links:${USER_A}`, JSON.stringify([1, 2]));
  assert.deepEqual(ks.get(USER_A), {});

  storage.setItem(`zk:links:${USER_A}`, JSON.stringify({ [id('a')]: secret('x'), 'mal': secret('y'), [id('b')]: 123, [id('c')]: '<script>' }));
  assert.deepEqual(ks.get(USER_A), { [id('a')]: secret('x') });
});

test('olvidar una clave', () => {
  const ks = createKeyStore(new FakeStorage());
  ks.save(USER_A, id('a'), secret('x'));
  ks.save(USER_A, id('b'), secret('y'));
  ks.forget(USER_A, id('a'));
  assert.deepEqual(Object.keys(ks.get(USER_A)), [id('b')]);
});

test('prune elimina las claves de pastes que ya no existen', () => {
  const ks = createKeyStore(new FakeStorage());
  ks.save(USER_A, id('a'), secret('x'));
  ks.save(USER_A, id('b'), secret('y'));
  ks.prune(USER_A, new Set([id('b')]));
  assert.deepEqual(Object.keys(ks.get(USER_A)), [id('b')]);
});

test('olvidar todo borra las claves de TODAS las cuentas y solo las nuestras', () => {
  const storage = new FakeStorage();
  const ks = createKeyStore(storage);
  ks.save(USER_A, id('a'), secret('x'));
  ks.save(USER_B, id('b'), secret('y'));
  storage.setItem('otra-app:ajuste', 'valor');

  assert.equal(ks.forgetAll(), 2);
  assert.deepEqual(ks.get(USER_A), {});
  assert.deepEqual(ks.get(USER_B), {});
  assert.equal(storage.getItem('otra-app:ajuste'), 'valor');
});

test('si el almacenamiento falla (cuota llena), save devuelve false y no lanza', () => {
  const storage = new FakeStorage();
  storage.setItem = () => { throw new Error('QuotaExceededError'); };
  assert.equal(createKeyStore(storage).save(USER_A, id('a'), secret('x')), false);
});

test('sin almacenamiento (null) todo falla de forma segura', () => {
  const ks = createKeyStore(null);
  assert.equal(ks.save(USER_A, id('a'), secret('x')), false);
  assert.deepEqual(ks.get(USER_A), {});
  assert.equal(ks.forgetAll(), 0);
});

test('olvidar claves no borra la preferencia de tema', () => {
  const storage = new FakeStorage();
  const ks = createKeyStore(storage);
  ks.save(USER_A, id('a'), secret('x'));
  storage.setItem('zk:theme', 'dark');
  storage.setItem('zk:palette', 'rose');

  ks.forgetAll();

  assert.equal(storage.getItem('zk:theme'), 'dark');
  assert.equal(storage.getItem('zk:palette'), 'rose');
});