const db = require('../db');

// Recomputes line items & total straight from the DB — the client-submitted cart is
// only ever treated as a list of {workId, qty} references, never as a source of price/status truth.
function buildLineItems(cartItems) {
  if (!Array.isArray(cartItems) || cartItems.length === 0) {
    return { error: 'Warenkorb ist leer.' };
  }
  const lineItems = [];
  for (const entry of cartItems) {
    const workId = Number(entry && entry.workId);
    const qty = Number(entry && entry.qty);
    if (!workId || !qty || qty < 1) return { error: 'Ungültiger Warenkorb-Eintrag.' };
    const w = db.prepare('SELECT * FROM works WHERE id = ?').get(workId);
    if (!w) return { error: `Werk ${workId} existiert nicht mehr.` };
    if (w.status === 'verkauft') return { error: `„${w.title}" ist bereits verkauft.` };
    const maxQty = w.kind === 'unique' ? 1 : 99;
    if (qty > maxQty) return { error: `„${w.title}" ist nur bis zu ${maxQty}× verfügbar.` };
    const metaLine = w.kind === 'unique'
      ? `${w.technique} · ${w.width_cm} × ${w.height_cm} cm`
      : `${w.edition_label} · ${w.technique}`;
    lineItems.push({
      workId: w.id,
      title: w.title,
      metaLine,
      unitPriceCents: w.price_cents,
      qty,
      kind: w.kind,
    });
  }
  const totalCents = lineItems.reduce((sum, li) => sum + li.unitPriceCents * li.qty, 0);
  if (totalCents <= 0) return { error: 'Bestellwert muss größer als 0 sein.' };
  return { lineItems, totalCents };
}

function validateCustomer(customer) {
  const required = ['firstName', 'lastName', 'email', 'street', 'zip', 'city'];
  const c = customer || {};
  for (const key of required) {
    if (!c[key] || String(c[key]).trim() === '') {
      return { error: 'Bitte alle Kontakt- und Lieferdaten ausfüllen.' };
    }
  }
  if (!/^\S+@\S+\.\S+$/.test(c.email)) return { error: 'Bitte eine gültige E-Mail-Adresse angeben.' };
  return { customer: c };
}

module.exports = { buildLineItems, validateCustomer };
