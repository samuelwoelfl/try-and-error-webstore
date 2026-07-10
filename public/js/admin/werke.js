function rowHtml(w) {
  const thumb = w.imageUrl ? `<img src="${escapeHtml(w.imageUrl)}" alt="">` : '';
  const meta = w.kind === 'edition' ? escapeHtml(w.editionLabel) : `${w.widthCm} × ${w.heightCm} cm`;
  const sold = w.status === 'verkauft';
  return `
    <div class="admin-table__row" data-id="${w.id}">
      <div class="admin-table__thumb">${thumb}</div>
      <div>
        <div class="admin-table__title">${escapeHtml(w.title)}</div>
        <div class="admin-table__meta">${meta}</div>
      </div>
      <span class="admin-table__technique">${escapeHtml(w.technique)}</span>
      <span class="admin-table__price">${fmtEuro(w.priceCents)}</span>
      <button type="button" class="pill pill--clickable admin-status-pill ${sold ? 'pill--sold' : 'pill--available'}" data-toggle="${w.id}">${sold ? 'Verkauft' : 'Verfügbar'}</button>
      <div class="admin-table__actions">
        <a href="/admin/werk-editor.html?id=${w.id}" class="admin-table__edit">Bearb.</a>
        <button type="button" class="admin-table__delete" data-del="${w.id}">Löschen</button>
      </div>
    </div>`;
}

async function loadWorks() {
  const container = document.getElementById('works-rows');
  try {
    const works = await api.adminWorks();
    if (works.length === 0) {
      container.innerHTML = '<div class="admin-table__empty">Noch keine Werke angelegt.</div>';
      return;
    }
    container.innerHTML = works.map(rowHtml).join('');
    container.querySelectorAll('[data-toggle]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        showAdminError(null);
        try {
          await api.adminToggleStatus(btn.dataset.toggle);
          loadWorks();
        } catch (e) { showAdminError(e.message); }
      });
    });
    container.querySelectorAll('[data-del]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Dieses Werk wirklich löschen?')) return;
        showAdminError(null);
        try {
          await api.adminDeleteWork(btn.dataset.del);
          flashSaved();
          loadWorks();
        } catch (e) { showAdminError(e.message); }
      });
    });
  } catch (e) {
    container.innerHTML = `<div class="admin-table__empty">Werke konnten nicht geladen werden.</div>`;
  }
}

(async () => {
  const session = await requireAdminAuth();
  if (!session) return;
  loadWorks();
})();
