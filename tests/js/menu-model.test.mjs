import test from 'node:test';
import assert from 'node:assert/strict';
import { menuItems } from '../../assets-src/js/menu-model.js';

const labels = (permissions) => menuItems(permissions).map((i) => i.label);

test('un usuario sin permisos solo ve crear paste', () => {
  assert.deepEqual(labels([]), ['Nuevo paste']);
});

test('member ve sus pastes y sus tags', () => {
  assert.deepEqual(
    labels(['paste.create', 'paste.list_own', 'tag.manage_own']),
    ['Nuevo paste', 'Mis pastes', 'Tags'],
  );
});

test('borrar pastes ajenos no abre la cola de moderación (cada acción, su permiso)', () => {
  assert.ok(!labels(['paste.delete_any']).includes('Moderación'));
  assert.ok(labels(['report.review']).includes('Moderación'));
});

test('administración aparece con cualquiera de sus dos permisos; auditoría, solo con audit.read', () => {
  assert.ok(labels(['user.manage']).includes('Administración'));
  assert.ok(labels(['role.manage']).includes('Administración'));
  assert.ok(!labels(['user.manage', 'role.manage']).includes('Auditoría'));
  assert.ok(labels(['audit.read']).includes('Auditoría'));
});

test('el admin completo ve todo, sin duplicados', () => {
  const all = labels(['paste.list_own', 'tag.manage_own', 'report.review', 'user.manage', 'role.manage', 'audit.read']);
  assert.deepEqual(all, ['Nuevo paste', 'Mis pastes', 'Tags', 'Moderación', 'Administración', 'Auditoría']);
});