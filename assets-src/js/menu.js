import { api } from './api.js';
import { menuItems } from './menu-model.js';
import { el } from './ui.js';

export function mountMenu(me, { logout = true } = {}) {
  const nav = document.getElementById('menu');
  if (!nav) return;

  nav.replaceChildren(
    ...menuItems(me.permissions).map((item) =>
      el('a', { href: item.href, 'aria-current': item.href === location.pathname ? 'page' : false }, item.label)),
  );
  if (logout) {
    nav.append(el('button', {
      type: 'button',
      onclick: async () => {
        try {
          await api('POST', '/auth/logout');
        } finally {
          location.href = '/login';
        }
      },
    }, 'Salir'));
  }
  nav.hidden = false;
}

export async function boot(options) {
  const me = await api('GET', '/auth/me');
  mountMenu(me, options);

  return me;
}