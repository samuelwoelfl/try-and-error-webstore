const db = require('../db');
const { sendMail } = require('./mailer');
const { fmtEuro } = require('./money');

// Called only after a payment provider has confirmed the charge (Stripe webhook,
// PayPal capture). Marks the order paid and takes unique (one-of-a-kind) works off
// the market so they can't be sold twice. Edition works have no fixed stock ledger here.
function finalizeOrderPaid(orderId) {
  const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(orderId);
  if (!order) return null;
  if (order.status === 'paid') return order; // idempotent — webhooks can fire more than once

  const markPaid = db.transaction(() => {
    db.prepare("UPDATE orders SET status = 'paid', paid_at = datetime('now') WHERE id = ?").run(orderId);
    const items = db.prepare('SELECT * FROM order_items WHERE order_id = ?').all(orderId);
    for (const item of items) {
      if (item.kind === 'unique') {
        db.prepare("UPDATE works SET status = 'verkauft' WHERE id = ?").run(item.workId ?? item.work_id);
      }
    }
    return items;
  });
  const items = markPaid();

  const updated = db.prepare('SELECT * FROM orders WHERE id = ?').get(orderId);

  const lines = items.map((i) => `${i.qty}× ${i.title} — ${fmtEuro(i.unit_price_cents * i.qty)}`).join('\n');
  sendMail({
    to: updated.email,
    subject: `Bestellbestätigung ${updated.order_number}`,
    text: `Hallo ${updated.first_name},\n\nvielen Dank für deine Bestellung!\n\n${lines}\n\nGesamt: ${fmtEuro(updated.total_cents)}\nBestellnummer: ${updated.order_number}\n\nWir melden uns mit dem Versand.`,
  }).catch((err) => console.error('[finalizeOrder] E-Mail-Versand fehlgeschlagen:', err));

  return updated;
}

module.exports = { finalizeOrderPaid };
