const BASE = { 'X-Requested-With': 'fetch' };

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message ?? `Error ${status}`);
    this.status = status;
    this.code = body?.error;
  }
}

export async function api(method, url, body) {
  const init = { method, headers: { ...BASE }, cache: 'no-store' };
  if (body !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  }

  const res = await fetch(url, init);
  if (res.status === 401) {
    location.href = '/login';
    throw new ApiError(401, { message: 'Tenés que iniciar sesión para continuar.' });
  }
  if (res.status === 204) return null;

  const data = await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(res.status, data);

  return data;
}