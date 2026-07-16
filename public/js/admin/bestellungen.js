const PAY_LABELS = { stripe: 'Kredit-/Debitkarte', paypal: 'PayPal', invoice: 'Rechnung/Überweisung' };

const STRIPE_ICON = '<svg width="12" height="12" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="14" height="14" rx="3" fill="#635BFF"/><text x="7" y="10.3" text-anchor="middle" font-family="Arial, sans-serif" font-size="9" font-weight="700" fill="#fff">S</text></svg>';

function paymentCellHtml(o) {
  if (o.status === 'cancelled') return '<span class="pill pill--order-cancelled">Storniert</span>';
  if (o.status === 'failed') return '<span class="pill pill--order-failed">Fehlgeschlagen</span>';

  const paid = o.status === 'paid';
  if (o.paymentMethod === 'stripe') {
    // Automatic — only ever shown once Stripe itself has confirmed the charge, never editable here.
    return paid ? `<span class="pill pill--order-paid pill--tag-icon">${STRIPE_ICON}Bezahlt</span>` : '';
  }
  return `
    <label class="pill pill--checkbox ${paid ? 'pill--order-paid' : 'pill--order-pending'}">
      <input type="checkbox" data-toggle-paid="${o.id}" ${paid ? 'checked' : ''}>
      Bezahlt
    </label>`;
}

function shippingCellHtml(o) {
  const fulfilled = !!o.fulfilledAt;
  return `
    <label class="pill pill--checkbox ${fulfilled ? 'pill--order-paid' : 'pill--order-pending'}">
      <input type="checkbox" data-toggle-fulfilled="${o.id}" ${fulfilled ? 'checked' : ''}>
      Versendet
    </label>`;
}

function rowHtml(o) {
  const itemsLine = o.items.map((i) => `${i.qty}× ${i.title}`).join(', ');
  const cancelBtn = o.status === 'cancelled'
    ? ''
    : `<button type="button" class="admin-table__cancel" data-cancel="${o.id}" title="Stornieren" aria-label="Stornieren">${ICON_CANCEL}</button>`;
  return `
    <div class="admin-table__row" data-id="${o.id}">
      <span class="admin-table__meta">${escapeHtml(fmtDateTime(o.createdAt))}</span>
      <div>
        <div class="admin-table__title">${escapeHtml(o.orderNumber)}</div>
        <div class="admin-table__meta">${escapeHtml(o.firstName)} ${escapeHtml(o.lastName)} · ${escapeHtml(o.email)}</div>
      </div>
      <div class="admin-table__meta">${escapeHtml(o.street)}<br>${escapeHtml(o.zip)} ${escapeHtml(o.city)}</div>
      <div class="admin-table__meta">${escapeHtml(itemsLine)}</div>
      <div class="admin-table__price">${fmtEuro(o.totalCents)}</div>
      <div class="admin-table__payment-col">
        <div class="admin-table__meta">${escapeHtml(PAY_LABELS[o.paymentMethod] || o.paymentMethod)}</div>
        ${paymentCellHtml(o)}
      </div>
      ${shippingCellHtml(o)}
      <div class="admin-table__actions">
        ${cancelBtn}
        <button type="button" class="admin-table__delete" data-del="${o.id}" title="Löschen" aria-label="Löschen">${ICON_DELETE}</button>
      </div>
    </div>`;
}

async function loadOrders() {
  const container = document.getElementById('orders-rows');
  try {
    const orders = await api.adminOrders();
    if (orders.length === 0) {
      container.innerHTML = '<div class="admin-table__empty">Noch keine Bestellungen eingegangen.</div>';
      return;
    }
    container.innerHTML = orders.map(rowHtml).join('');
    container.querySelectorAll('[data-cancel]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Diese Bestellung wirklich stornieren?')) return;
        showAdminError(null);
        try {
          await api.adminCancelOrder(btn.dataset.cancel);
          flashSaved();
          loadOrders();
        } catch (e) { showAdminError(e.message); }
      });
    });
    container.querySelectorAll('[data-toggle-paid]').forEach((checkbox) => {
      checkbox.addEventListener('change', async () => {
        showAdminError(null);
        try {
          await api.adminToggleOrderPaid(checkbox.dataset.togglePaid);
          loadOrders();
        } catch (e) { showAdminError(e.message); loadOrders(); }
      });
    });
    container.querySelectorAll('[data-toggle-fulfilled]').forEach((checkbox) => {
      checkbox.addEventListener('change', async () => {
        showAdminError(null);
        try {
          await api.adminToggleOrderFulfilled(checkbox.dataset.toggleFulfilled);
          loadOrders();
        } catch (e) { showAdminError(e.message); loadOrders(); }
      });
    });
    container.querySelectorAll('[data-del]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Diese Bestellung wirklich endgültig löschen?')) return;
        showAdminError(null);
        try {
          await api.adminDeleteOrder(btn.dataset.del);
          flashSaved();
          loadOrders();
        } catch (e) { showAdminError(e.message); }
      });
    });
  } catch (e) {
    container.innerHTML = '<div class="admin-table__empty">Bestellungen konnten nicht geladen werden.</div>';
  }
}

(async () => {
  const session = await requireAdminAuth();
  if (!session) return;
  loadOrders();
})();
