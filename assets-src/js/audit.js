import { api } from './api.js';
import { boot } from './menu.js';
import { describeError, el, fmt, notifier } from './ui.js';

const $ = (id) => document.getElementById(id);
const note = notifier($('error'), $('status'));
const code = (value) => (value ? el('code', {}, value) : '—');

const ACTIONS = {
  role_assigned: 'Rol asignado',
  role_revoked: 'Rol quitado',
  permission_granted: 'Permiso concedido',
  permission_revoked: 'Permiso quitado',
};
const REASONS = {
  owner: 'Propietario',
  moderator: 'Moderación',
  token: 'Token de borrado',
  expired: 'Vencido',
  burned: 'Leído (lectura única)',
};

async function main() {
  await boot();

  const [{ actions }, { deletions }] = await Promise.all([
    api('GET', '/admin/audit/actions'),
    api('GET', '/admin/audit/deletions'),
  ]);

  $('actions').replaceChildren(...actions.map((a) => el('tr', {},
    el('td', {}, fmt(a.occurredAt)),
    el('td', {}, ACTIONS[a.action] ?? a.action),
    el('td', {}, code(a.actorId)),
    el('td', {}, code(a.targetUserId)),
    el('td', {}, a.roleName),
    el('td', {}, code(a.permissionCode)),
  )));

  $('deletions').replaceChildren(...deletions.map((d) => el('tr', {},
    el('td', {}, fmt(d.deletedAt)),
    el('td', {}, code(d.pasteId)),
    el('td', {}, REASONS[d.reason] ?? d.reason),
    el('td', {}, code(d.actorId)),
  )));
}

main().catch((err) => note.error(describeError(err, 'Tu cuenta no tiene permiso para ver la auditoría (falta audit.read).')));