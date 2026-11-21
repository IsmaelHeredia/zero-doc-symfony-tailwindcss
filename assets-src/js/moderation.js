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

async function load() {
  const { reports } = await api('GET', '/moderation/reports');
  $('empty').hidden = reports.length > 0;
  $('reports').replaceChildren(...reports.map(row));
}

function row(report) {
  return el('tr', {},
    el('td', {}, el('code', {}, report.pasteId)),
    el('td', {}, report.reason),
    el('td', {}, report.comment ?? '—'),
    el('td', {}, fmt(report.createdAt)),
    el('td', {}, String(report.openReportsForPaste)),
    el('td', {},
      el('button', { type: 'button', onclick: () => dismiss(report) }, 'Descartar'),
      ' ',
      el('button', { type: 'button', class: 'btn-danger', onclick: () => destroy(report) }, 'Destruir paste'),
    ),
  );
}

const dismiss = (report) => guarded(async () => {
  await api('POST', `/moderation/reports/${seg(report.id)}/dismiss`);
  await load();
  note.ok('Descartamos el reporte.');
});

async function destroy(report) {
  if (!confirm(`¿Querés destruir el paste ${report.pasteId}? La acción es irreversible y cierra todos sus reportes.`)) return;

  await guarded(async () => {
    await api('DELETE', `/pastes/${seg(report.pasteId)}`);
    await load();
    note.ok('Destruimos el paste. La acción quedó registrada en la auditoría.');
  }, 'Tu cuenta puede revisar reportes, pero no destruir pastes ajenos (falta el permiso paste.delete_any).');
}

guarded(async () => {
  await boot();
  await load();
}, 'Tu cuenta no tiene permiso para revisar reportes.');