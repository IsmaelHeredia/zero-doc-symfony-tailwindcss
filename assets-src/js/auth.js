const form = document.getElementById('auth-form');
const errorBox = document.getElementById('error');

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.hidden = true;

  try {
    const res = await fetch(form.dataset.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.fromEntries(new FormData(form))),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message ?? data.error ?? 'No se pudo completar la operación.');

    location.href = form.dataset.next;
  } catch (err) {
    errorBox.textContent = err.message;
    errorBox.hidden = false;
  }
});