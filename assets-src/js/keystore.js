const PREFIX = 'zk:links:';
const USER_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const PASTE_ID = /^[A-Za-z0-9_-]{22}$/;
const LINK_SECRET = /^[A-Za-z0-9_-]{43}$/;

export function createKeyStore(storage) {
  const keyOf = (userId) => {
    if (!USER_ID.test(userId)) throw new Error('userId inválido');
    return PREFIX + userId;
  };

  function read(userId) {
    try {
      const raw = storage.getItem(keyOf(userId));
      if (!raw) return {};
      const parsed = JSON.parse(raw);
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) return {};

      const clean = {};
      for (const [id, secret] of Object.entries(parsed)) {
        if (PASTE_ID.test(id) && typeof secret === 'string' && LINK_SECRET.test(secret)) clean[id] = secret;
      }
      return clean;
    } catch {
      return {};
    }
  }

  function write(userId, map) {
    try {
      storage.setItem(keyOf(userId), JSON.stringify(map));
      return true;
    } catch {
      return false;
    }
  }

  return {
    save(userId, pasteId, secret) {
      if (!PASTE_ID.test(pasteId) || !LINK_SECRET.test(secret)) return false;
      return write(userId, { ...read(userId), [pasteId]: secret });
    },

    get: read,

    forget(userId, pasteId) {
      const { [pasteId]: _removed, ...rest } = read(userId);
      return write(userId, rest);
    },

    prune(userId, aliveIds) {
      const current = read(userId);
      const kept = Object.fromEntries(Object.entries(current).filter(([id]) => aliveIds.has(id)));
      if (Object.keys(kept).length !== Object.keys(current).length) write(userId, kept);
    },

    forgetAll() {
      try {
        const ours = [];
        for (let i = 0; i < storage.length; i++) {
          const k = storage.key(i);
          if (k && k.startsWith(PREFIX)) ours.push(k);
        }
        ours.forEach((k) => storage.removeItem(k));
        return ours.length;
      } catch {
        return 0;
      }
    },
  };
}

function browserStorage() {
  try {
    return globalThis.localStorage ?? null;
  } catch {
    return null;
  }
}

export function browserKeyStore() {
  return createKeyStore(browserStorage());
}