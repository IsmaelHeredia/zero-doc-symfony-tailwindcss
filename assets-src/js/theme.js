(() => {
  const THEME_KEY = 'zk:theme';
  const PALETTE_KEY = 'zk:palette';
  const PALETTES = ['indigo', 'emerald', 'rose', 'amber'];
  const root = document.documentElement;
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  let themeButton = null;

  const read = (key) => {
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  };
  const write = (key, value) => {
    try {
      localStorage.setItem(key, value);
    } catch {
      return;
    }
  };

  const storedMode = () => {
    const value = read(THEME_KEY);

    return value === 'light' || value === 'dark' ? value : null;
  };

  function syncButton() {
    if (!themeButton) return;
    const dark = root.classList.contains('dark');
    const label = dark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
    themeButton.setAttribute('aria-pressed', String(dark));
    themeButton.setAttribute('aria-label', label);
    themeButton.title = label;
  }

  function apply() {
    root.classList.toggle('dark', (storedMode() ?? (media.matches ? 'dark' : 'light')) === 'dark');

    const palette = read(PALETTE_KEY);
    if (PALETTES.includes(palette) && palette !== 'indigo') root.dataset.palette = palette;
    else delete root.dataset.palette;

    syncButton();
  }

  const SVG_NS = 'http://www.w3.org/2000/svg';

  function icon(className, shapes) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    const attrs = {
      class: className, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
    };
    for (const [name, value] of Object.entries(attrs)) svg.setAttribute(name, value);
    for (const [tag, shapeAttrs] of shapes) {
      const shape = document.createElementNS(SVG_NS, tag);
      for (const [name, value] of Object.entries(shapeAttrs)) shape.setAttribute(name, value);
      svg.append(shape);
    }

    return svg;
  }

  function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attrs)) node.setAttribute(name, value);
    for (const child of children.flat()) node.append(child instanceof Node ? child : document.createTextNode(String(child)));

    return node;
  }

  const ICONS = {
    help: [
      ['circle', { cx: '12', cy: '12', r: '10' }],
      ['path', { d: 'M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3' }],
      ['line', { x1: '12', y1: '17', x2: '12.01', y2: '17' }],
    ],
    about: [
      ['circle', { cx: '12', cy: '12', r: '10' }],
      ['line', { x1: '12', y1: '16', x2: '12', y2: '12' }],
      ['line', { x1: '12', y1: '8', x2: '12.01', y2: '8' }],
    ],
    close: [
      ['path', { d: 'M18 6 6 18' }],
      ['path', { d: 'M6 6l12 12' }],
    ],
    lock: [
      ['rect', { x: '3', y: '11', width: '18', height: '11', rx: '2', ry: '2' }],
      ['path', { d: 'M7 11V7a5 5 0 0 1 10 0v4' }],
    ],
  };

  const HELP = {
    id: 'help-modal',
    title: 'Ayuda',
    sections: [
      {
        heading: 'Uso básico',
        items: [
          ['Crear', 'escribí o pegá tu texto en la página principal y elegí cuándo vence y cuántas veces se puede leer.'],
          ['Enlace', 'al crear el paste vas a recibir un enlace que incluye la clave después del «#». Cualquier persona que tenga el enlace completo puede leerlo.'],
          ['Abrir', 'tocá «Mostrar contenido». Si el paste es de una sola lectura, se destruye en cuanto se muestra.'],
          ['Sin cuenta', 'el vencimiento máximo es de 7 días y el tamaño es acotado. Con una cuenta, tus pastes son tuyos, pueden ser permanentes y admiten más tamaño.'],
        ],
      },
      {
        heading: 'Opciones del paste',
        items: [
          ['Vencimiento', '10 minutos, 1 hora, 1 día, 7 días o «Nunca» (requiere un permiso de tu cuenta).'],
          ['Lecturas', 'sin límite, una sola (se destruye al abrirlo), 3 o 10.'],
          ['Contraseña', 'es opcional. Además del enlace, se va a necesitar la contraseña; te recomendamos compartirla por otro medio.'],
          ['Recordar enlace', 'si tenés cuenta, guarda la clave en este navegador para que puedas verlo después en «Mis pastes».'],
        ],
      },
      {
        heading: 'Contraseña y claves',
        text: 'La contraseña no se puede recuperar: ni el servidor ni el administrador la conocen. Si te equivocás al escribirla, podés volver a intentar sin gastar otra lectura, siempre que no recargues la página.',
        code: 'Clave AES = PBKDF2-SHA256(contraseña, sal + secreto del enlace)\n600 000 iteraciones o más · todo ocurre en tu navegador',
      },
      {
        heading: 'Borrar un paste',
        items: [
          ['Sin cuenta', 'usá el token de borrado que se muestra una única vez al crear el paste. Guardalo en un lugar seguro.'],
          ['Con cuenta', 'quien lo creó puede borrarlo desde la página del paste con la sesión iniciada.'],
          ['Moderación', 'el equipo de moderación puede destruir un paste a partir de su ID sin leerlo, y la acción queda registrada en la auditoría.'],
        ],
      },
      {
        heading: 'Cuenta y «Mis pastes»',
        text: 'El servidor guarda solamente metadatos. Los enlaces completos se reconstruyen con las claves almacenadas en este navegador.',
        items: [
          ['Equipo compartido', 'elegí «Salir y olvidar claves» o desmarcá «Recordar el enlace».'],
          ['Otro navegador', 'los pastes creados desde otro dispositivo aparecen en la lista, pero sin la clave disponible.'],
        ],
      },
      {
        heading: 'Tags, reportes y administración',
        items: [
          ['Tags', 'te permiten organizar tus propios pastes. Sus nombres se guardan en texto plano y los puede ver quien administre el servidor.'],
          ['Reportar', 'desde la página de un paste podés avisar si encontrás contenido abusivo. Solo se envía el ID, nunca la clave.'],
          ['Menú', 'Moderación, Administración y Auditoría aparecen únicamente si tu cuenta tiene los permisos correspondientes.'],
        ],
      },
      {
        heading: 'Atajos y apariencia',
        steps: [
          'Presioná «?» (fuera de un campo de texto) para abrir esta ayuda.',
          'Usá el botón del sol y la luna para alternar entre el tema claro y el oscuro; tu elección queda guardada.',
        ],
        note: 'Si no elegís un tema, la aplicación sigue el de tu sistema.',
      },
    ],
    close: 'Entendido',
  };

  const ABOUT = {
    id: 'about-modal',
    title: 'Acerca de',
    brand: { name: 'ZERODOC', tagline: 'Compartí texto cifrado desde tu navegador' },
    meta: [
      ['Versión', '1.0'],
      ['Autor', 'Ismael Heredia'],
    ],
    blocks: [
      [
        'Descripción',
        'ZeroDoc es una herramienta web de tipo «zero-knowledge»: el texto se cifra en tu navegador con AES-GCM antes de enviarse, y el servidor solo almacena datos cifrados. La clave viaja en el fragmento del enlace, que el navegador no envía. Incluye vencimiento, lectura única, contraseña opcional, tags, reportes de abuso y un registro de auditoría sin contenido.',
      ],
      [
        'Límites conocidos',
        'El servidor entrega el código JavaScript, algo que se mitiga con una política de seguridad estricta y verificación de integridad. Quien tenga el enlace, y la contraseña si existe, puede leer el paste. Los metadatos y los tags se guardan en texto plano.',
      ],
    ],
    close: 'Cerrar',
  };

  const dialogs = new Map();

  function renderSection(section) {
    const node = el('section', { class: 'modal-section' }, el('h3', {}, section.heading));
    if (section.text) node.append(el('p', { class: 'modal-text' }, section.text));
    if (section.items) {
      node.append(el('ul', { class: 'modal-list' }, section.items.map(([label, text]) =>
        el('li', {}, el('strong', { class: 'modal-label' }, label), ` — ${text}`))));
    }
    if (section.steps) node.append(el('ol', { class: 'modal-steps' }, section.steps.map((step) => el('li', {}, step))));
    if (section.code) node.append(el('pre', { class: 'modal-code' }, section.code));
    if (section.note) node.append(el('p', { class: 'modal-note' }, section.note));

    return node;
  }

  function buildHelp(spec) {
    const closeX = el('button', { type: 'button', class: 'icon-btn modal-close', 'aria-label': 'Cerrar' });
    closeX.append(icon('', ICONS.close));
    const done = el('button', { type: 'button', class: 'btn-primary' }, spec.close);

    const dialog = el('dialog', { class: 'modal', 'aria-labelledby': `${spec.id}-title` },
      el('div', { class: 'modal-header' }, el('h2', { id: `${spec.id}-title`, class: 'modal-title' }, spec.title), closeX),
      el('div', { class: 'modal-body' }, spec.sections.map(renderSection)),
      el('div', { class: 'modal-footer' }, done),
    );
    closeX.addEventListener('click', () => dialog.close());
    done.addEventListener('click', () => dialog.close());

    return dialog;
  }

  function buildAbout(spec) {
    const logo = el('div', { class: 'modal-logo' });
    logo.append(icon('', ICONS.lock));
    const done = el('button', { type: 'button', class: 'btn-primary' }, spec.close);

    const dialog = el('dialog', { class: 'modal', 'aria-labelledby': `${spec.id}-title` },
      el('div', { class: 'modal-body' },
        el('div', { class: 'modal-brand' }, logo,
          el('div', {},
            el('h2', { id: `${spec.id}-title`, class: 'modal-title' }, spec.brand.name),
            el('p', { class: 'modal-note' }, spec.brand.tagline))),
        el('div', { class: 'modal-grid' }, spec.meta.map(([label, value]) =>
          el('div', {}, el('p', { class: 'modal-kicker' }, label), el('p', { class: 'modal-value' }, value)))),
        spec.blocks.map(([label, text]) =>
          el('div', { class: 'modal-block' }, el('p', { class: 'modal-kicker' }, label), el('p', { class: 'modal-text' }, text))),
      ),
      el('div', { class: 'modal-footer' }, done),
    );
    done.addEventListener('click', () => dialog.close());

    return dialog;
  }

  function openDialog(spec, build) {
    for (const other of dialogs.values()) if (other.open) other.close();
    if (!dialogs.has(spec.id)) {
      const dialog = build(spec);
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
      });
      document.body.append(dialog);
      dialogs.set(spec.id, dialog);
    }
    const dialog = dialogs.get(spec.id);
    if (!dialog.open) dialog.showModal();
    dialog.querySelector('.modal-body')?.scrollTo(0, 0);
  }

  const openHelp = () => openDialog(HELP, buildHelp);
  const openAbout = () => openDialog(ABOUT, buildAbout);

  function iconButton(onClick, shapes, label) {
    const button = el('button', {
      type: 'button', class: 'icon-btn', 'aria-label': label, title: label, 'aria-haspopup': 'dialog',
    });
    button.append(icon('', shapes));
    button.addEventListener('click', onClick);

    return button;
  }

  function themeToggle() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'theme-toggle';
    button.append(
      icon('sun', [
        ['circle', { cx: '12', cy: '12', r: '4' }],
        ['path', { d: 'M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41' }],
      ]),
      icon('moon', [['path', { d: 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z' }]]),
    );
    button.addEventListener('click', () => {
      write(THEME_KEY, root.classList.contains('dark') ? 'light' : 'dark');
      apply();
    });

    return button;
  }

  function mount() {
    const slot = document.getElementById('theme-slot');
    if (!slot) return;

    themeButton = themeToggle();
    slot.replaceChildren(el('div', { class: 'toolbar' },
      iconButton(openHelp, ICONS.help, 'Ayuda'),
      iconButton(openAbout, ICONS.about, 'Acerca de'),
      themeButton,
    ));
    syncButton();

    document.addEventListener('keydown', (event) => {
      if (event.key !== '?' || event.ctrlKey || event.metaKey || event.altKey) return;
      const target = event.target;
      if (target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))) return;
      event.preventDefault();
      openHelp();
    });
  }

  apply();
  media.addEventListener('change', apply);
  window.addEventListener('storage', (event) => {
    if (event.key === THEME_KEY || event.key === PALETTE_KEY) apply();
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else mount();
})();