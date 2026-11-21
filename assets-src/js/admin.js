import { api } from './api.js';
import { boot } from './menu.js';
import { describeError, el, fmt, notifier } from './ui.js';

const $ = (id) => document.getElementById(id);
const note = notifier($('error'), $('status'));
const seg = encodeURIComponent;

async function guarded(fn, forbidden) {
  note.clear();
  try {
    return await fn();
  } catch (err) {
    note.error(describeError(err, forbidden));

    return undefined;
  }
}

async function loadUsers() {
  const { users, availableRoles, truncated } = await api('GET', '/admin/users');
  $('users-truncated').hidden = !truncated;
  $('users').replaceChildren(...users.map((u) => userRow(u, availableRoles)));
}

function userRow(user, availableRoles) {
  const missing = availableRoles.filter((r) => !user.roles.includes(r));
  const select = el('select', {}, missing.map((r) => el('option', { value: r }, r)));

  return el('tr', {},
    el('td', {}, user.email),
    el('td', {}, el('code', {}, user.id)),
    el('td', {}, user.roles.map((role) => el('span', { class: 'chip' }, role, ' ',
      el('button', { type: 'button', 'aria-label': `Quitar el rol ${role}`, onclick: () => revokeRole(user, role) }, '×')))),
    el('td', {}, fmt(user.createdAt)),
    el('td', {}, missing.length === 0 ? '—' : [select, ' ',
      el('button', { type: 'button', onclick: () => assignRole(user, select.value) }, 'Asignar')]),
  );
}

const assignRole = (user, role) => guarded(async () => {
  await api('PUT', `/admin/users/${seg(user.id)}/roles/${seg(role)}`);
  await loadUsers();
  note.ok(`Asignamos el rol ${role}.`);
});

async function revokeRole(user, role) {
  const extra = role === 'admin' ? ' Va a perder el acceso de administración.' : '';
  if (!confirm(`¿Querés quitarle el rol «${role}» a ${user.email}?${extra}`)) return;

  await guarded(async () => {
    await api('DELETE', `/admin/users/${seg(user.id)}/roles/${seg(role)}`);
    await loadUsers();
    note.ok(`Quitamos el rol ${role}.`);
  });
}

async function loadRoles() {
  const { roles, catalog, protectedAdminPermissions } = await api('GET', '/admin/roles');
  $('roles').replaceChildren(...roles.map((r) => roleBlock(r, catalog, protectedAdminPermissions)));
}

function roleBlock(role, catalog, protectedForAdmin) {
  const missing = catalog.filter((code) => !role.permissions.includes(code));
  const select = el('select', {}, missing.map((code) => el('option', { value: code }, code)));

  return el('section', { class: 'card stack' },
    el('h3', {}, role.name),
    el('ul', { class: 'list' }, role.permissions.map((code) => {
      const locked = role.name === 'admin' && protectedForAdmin.includes(code);

      return el('li', {}, el('code', {}, code), ' ',
        locked ? '(protegido)' : el('button', { type: 'button', onclick: () => revokePermission(role.name, code) }, 'Quitar'));
    })),
    missing.length === 0 ? '' : el('div', {}, select, ' ',
      el('button', { type: 'button', onclick: () => grantPermission(role.name, select.value) }, 'Conceder')),
  );
}

async function grantPermission(role, code) {
  if (!confirm(`¿Querés conceder «${code}» al rol «${role}»? Afecta a todos los usuarios que tengan ese rol.`)) return;

  await guarded(async () => {
    await api('PUT', `/admin/roles/${seg(role)}/permissions/${seg(code)}`);
    await loadRoles();
    note.ok(`Concedimos el permiso ${code} al rol ${role}.`);
  });
}

async function revokePermission(role, code) {
  if (!confirm(`¿Querés quitarle «${code}» al rol «${role}»? Afecta a todos los usuarios que tengan ese rol.`)) return;

  await guarded(async () => {
    await api('DELETE', `/admin/roles/${seg(role)}/permissions/${seg(code)}`);
    await loadRoles();
    note.ok(`Quitamos el permiso ${code} al rol ${role}.`);
  });
}

guarded(async () => {
  const me = await boot();
  const canUsers = me.permissions.includes('user.manage');
  const canRoles = me.permissions.includes('role.manage');

  $('users-section').hidden = !canUsers;
  $('roles-section').hidden = !canRoles;
  if (!canUsers && !canRoles) {
    note.error('Tu cuenta no tiene permisos de administración.');

    return;
  }

  await Promise.all([canUsers ? loadUsers() : null, canRoles ? loadRoles() : null]);
});