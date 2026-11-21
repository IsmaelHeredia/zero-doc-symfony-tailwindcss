import { api } from './api.js';
import { boot } from './menu.js';
import { describeError, el, fmt, notifier } from './ui.js';

const $ = (id) => document.getElementById(id);
const note = notifier($('error'), $('status'));
const seg = encodeURIComponent;
let selected = null;

async function guarded(fn) {
  note.clear();
  try {
    return await fn();
  } catch (err) {
    note.error(describeError(err));

    return undefined;
  }
}

async function loadTags() {
  const { tags } = await api('GET', '/tags');
  $('no-tags').hidden = tags.length > 0;
  $('tags').replaceChildren(...tags.map((t) => el('li', {},
    el('button', { type: 'button', onclick: () => guarded(() => select(t)) }, `${t.name} (${t.pasteCount})`),
    ' ',
    el('button', { type: 'button', class: 'btn-danger', onclick: () => removeTag(t) }, 'Borrar tag'),
  )));

  return tags;
}

async function refresh() {
  const tags = await loadTags();
  const current = tags.find((t) => t.id === selected?.id) ?? null;
  if (current) {
    await select(current);
  } else {
    selected = null;
    $('detail').hidden = true;
  }
}

async function select(tag) {
  selected = tag;
  $('detail-title').textContent = tag.name;

  const { pastes } = await api('GET', `/tags/${seg(tag.id)}/pastes`);
  $('tag-empty').hidden = pastes.length > 0;
  $('tag-pastes').replaceChildren(...pastes.map((p) => el('li', {},
    el('code', {}, p.id), ` · creado el ${fmt(p.createdAt)} `,
    el('button', { type: 'button', onclick: () => detach(p.id) }, 'Quitar del tag'),
  )));

  let mine = [];
  try {
    mine = (await api('GET', '/pastes/mine')).pastes;
  } catch {
    mine = [];
  }
  const inTag = new Set(pastes.map((p) => p.id));
  const free = mine.filter((p) => !inTag.has(p.id));
  $('attach-select').replaceChildren(...free.map((p) => el('option', { value: p.id }, `${p.id} · ${fmt(p.createdAt)}`)));
  $('attach-block').hidden = free.length === 0;
  $('detail').hidden = false;
}

async function removeTag(tag) {
  if (!confirm(`¿Querés borrar el tag «${tag.name}»? Los pastes no se borran.`)) return;
  await guarded(async () => {
    await api('DELETE', `/tags/${seg(tag.id)}`);
    await refresh();
    note.ok('Borramos el tag.');
  });
}

async function detach(pasteId) {
  await guarded(async () => {
    await api('DELETE', `/tags/${seg(selected.id)}/pastes/${seg(pasteId)}`);
    await refresh();
  });
}

$('attach').addEventListener('click', () => guarded(async () => {
  const pasteId = $('attach-select').value;
  if (!pasteId) return;
  await api('PUT', `/tags/${seg(selected.id)}/pastes/${seg(pasteId)}`);
  await refresh();
}));

$('tag-form').addEventListener('submit', (event) => {
  event.preventDefault();
  guarded(async () => {
    await api('POST', '/tags', { name: $('tag-form').elements.name.value });
    $('tag-form').reset();
    await refresh();
    note.ok('Creamos el tag.');
  });
});

guarded(async () => {
  await boot();
  await loadTags();
});