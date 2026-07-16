const ICON_EDIT = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5l3 3L6 17H3v-3L13.5 3.5z"/></svg>';
const ICON_DELETE = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m-6.5 0 .6 9.4a1.5 1.5 0 0 0 1.5 1.4h4.8a1.5 1.5 0 0 0 1.5-1.4L14.5 6"/></svg>';
const ICON_CANCEL = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M7.3 7.3l5.4 5.4M12.7 7.3l-5.4 5.4"/></svg>';
const ICON_EYE = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.3"/></svg>';
const ICON_EYE_OFF = '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 10S5 4.3 10 4.3c1.5 0 2.9.4 4.1 1M17.5 10s-1 2-2.9 3.6M10 15.7c-5 0-7.5-5.7-7.5-5.7A13.6 13.6 0 0 1 5.3 6"/><path d="M8.3 8.3a2.3 2.3 0 0 0 3.2 3.2"/><path d="M3 3l14 14"/></svg>';

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
  el.classList.add('is-visible');
  clearTimeout(flashSaved._t);
  flashSaved._t = setTimeout(() => { el.classList.remove('is-visible'); }, 2200);
}

function showAdminError(msg) {
  const el = document.getElementById('admin-error');
  if (!el) return;
  if (!msg) { el.hidden = true; return; }
  el.hidden = false;
  el.textContent = msg;
}

document.addEventListener('DOMContentLoaded', wireLogout);
