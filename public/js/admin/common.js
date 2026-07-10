async function requireAdminAuth() {
  try {
    const s = await api.adminSession();
    if (!s.authed) { location.href = '/admin/login.html'; return null; }
    return s;
  } catch (e) {
    location.href = '/admin/login.html';
    return null;
  }
}

function wireLogout() {
  const btn = document.getElementById('logout-btn');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    await api.adminLogout();
    location.href = '/admin/login.html';
  });
}

function flashSaved() {
  const el = document.getElementById('admin-flash');
  if (!el) return;
  el.hidden = false;
  clearTimeout(flashSaved._t);
  flashSaved._t = setTimeout(() => { el.hidden = true; }, 2200);
}

function showAdminError(msg) {
  const el = document.getElementById('admin-error');
  if (!el) return;
  if (!msg) { el.hidden = true; return; }
  el.hidden = false;
  el.textContent = msg;
}

document.addEventListener('DOMContentLoaded', wireLogout);
