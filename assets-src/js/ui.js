export function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  for (const [name, value] of Object.entries(attrs)) {
    if (name.startsWith('on') && typeof value === 'function') node.addEventListener(name.slice(2), value);
    else if (value === true) node.setAttribute(name, '');
    else if (value !== false && value !== null && value !== undefined) node.setAttribute(name, value);
  }
  for (const child of children.flat()) node.append(child instanceof Node ? child : document.createTextNode(String(child)));

  return node;
}

export const fmt = (iso) => new Date(iso).toLocaleString();

export function notifier(errorNode, statusNode) {
  return {
    error(message) {
      statusNode.hidden = true;
      errorNode.textContent = message;
      errorNode.hidden = false;
    },
    ok(message) {
      errorNode.hidden = true;
      statusNode.textContent = message;
      statusNode.hidden = false;
    },
    clear() {
      errorNode.hidden = true;
      statusNode.hidden = true;
    },
  };
}

export function describeError(err, forbidden = 'Tu cuenta no tiene permiso para realizar esta acción.') {
  if (err?.status === 403) return err.code === 'csrf' ? 'La petición fue rechazada por seguridad (falta la cabecera CSRF).' : forbidden;

  return err?.message ?? 'Ocurrió un error inesperado. Probá de nuevo.';
}