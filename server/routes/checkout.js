const express = require('express');
const db = require('../db');
const { buildLineItems, validateCustomer } = require('../lib/orderBuilder');
const { orderNumber: genOrderNumber, fmtEuro } = require('../lib/money');
const stripeLib = require('../lib/stripe');
const paypalLib = require('../lib/paypal');
const { finalizeOrderPaid } = require('../lib/finalizeOrder');
const { sendMail } = require('../lib/mailer');

const router = express.Router();

function createPendingOrder({ lineItems, totalCents, customer, paymentMethod }) {
  const insertOrder = db.prepare(`
    INSERT INTO orders (order_number, status, payment_method, first_name, last_name, email, street, zip, city, total_cents, currency)
    VALUES (@order_number, 'pending', @payment_method, @first_name, @last_name, @email, @street, @zip, @city, @total_cents, 'eur')
  `);
  const insertItem = db.prepare(`
    INSERT INTO order_items (order_id, work_id, title, meta_line, unit_price_cents, qty, kind)
    VALUES (@order_id, @work_id, @title, @meta_line, @unit_price_cents, @qty, @kind)
  `);
  const tx = db.transaction(() => {
    const order_number = genOrderNumber();
    const info = insertOrder.run({
      order_number,
      payment_method: paymentMethod,
      first_name: customer.firstName,
      last_name: customer.lastName,
      email: customer.email,
      street: customer.street,
      zip: customer.zip,
      city: customer.city,
      total_cents: totalCents,
    });
    const orderId = info.lastInsertRowid;
    for (const li of lineItems) {
      insertItem.run({
        order_id: orderId,
        work_id: li.workId,
        title: li.title,
        meta_line: li.metaLine,
        unit_price_cents: li.unitPriceCents,
        qty: li.qty,
        kind: li.kind,
      });
    }
    return { orderId, order_number };
  });
  return tx();
}

function parseCheckoutBody(req, res) {
  const li = buildLineItems(req.body && req.body.cart);
  if (li.error) {
    res.status(400).json({ error: li.error });
    return null;
  }
  const cust = validateCustomer(req.body && req.body.customer);
  if (cust.error) {
    res.status(400).json({ error: cust.error });
    return null;
  }
  return { lineItems: li.lineItems, totalCents: li.totalCents, customer: cust.customer };
}

// ---- Stripe: create a PaymentIntent for the (server-computed) order total ----
router.post('/stripe/intent', async (req, res) => {
  if (!stripeLib.enabled) return res.status(503).json({ error: 'Kartenzahlung ist derzeit nicht verfügbar.' });
  const parsed = parseCheckoutBody(req, res);
  if (!parsed) return;
  try {
    const { orderId, order_number } = createPendingOrder({ ...parsed, paymentMethod: 'stripe' });
    const intent = await stripeLib.stripe.paymentIntents.create({
      amount: parsed.totalCents,
      currency: 'eur',
      automatic_payment_methods: { enabled: true },
      metadata: { orderId: String(orderId), orderNumber: order_number },
      receipt_email: parsed.customer.email,
    });
    db.prepare('UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?').run(intent.id, orderId);
    res.json({ clientSecret: intent.client_secret, orderNumber: order_number });
  } catch (err) {
    console.error('[checkout/stripe] Fehler:', err);
    res.status(500).json({ error: 'Zahlung konnte nicht vorbereitet werden.' });
  }
});

// ---- Stripe: fallback sync for local dev when no webhook forwarder (e.g. `stripe listen`) is
// running — the client calls this right after stripe.confirmPayment() resolves so the order
// doesn't stay stuck on "pending" waiting for a webhook that will never arrive locally. In
// production the webhook above is the source of truth; this is purely a redundant, idempotent check.
router.post('/stripe/sync/:paymentIntentId', async (req, res) => {
  if (!stripeLib.enabled) return res.status(503).json({ error: 'Kartenzahlung ist derzeit nicht verfügbar.' });
  try {
    const order = db.prepare('SELECT * FROM orders WHERE stripe_payment_intent_id = ?').get(req.params.paymentIntentId);
    if (!order) return res.status(404).json({ error: 'Bestellung nicht gefunden.' });
    const intent = await stripeLib.stripe.paymentIntents.retrieve(req.params.paymentIntentId);
    if (intent.status === 'succeeded') {
      finalizeOrderPaid(order.id);
      return res.json({ ok: true, orderNumber: order.order_number, status: 'paid' });
    }
    res.json({ ok: true, orderNumber: order.order_number, status: order.status });
  } catch (err) {
    console.error('[checkout/stripe/sync] Fehler:', err);
    res.status(500).json({ error: 'Status konnte nicht geprüft werden.' });
  }
});

// ---- PayPal: create a PayPal order mirroring our pending order ----
router.post('/paypal/create-order', async (req, res) => {
  if (!paypalLib.enabled) return res.status(503).json({ error: 'PayPal ist derzeit nicht verfügbar.' });
  const parsed = parseCheckoutBody(req, res);
  if (!parsed) return;
  try {
    const { orderId, order_number } = createPendingOrder({ ...parsed, paymentMethod: 'paypal' });
    const ppOrder = await paypalLib.createOrder({ amountCents: parsed.totalCents, currency: 'EUR', orderNumber: order_number });
    db.prepare('UPDATE orders SET paypal_order_id = ? WHERE id = ?').run(ppOrder.id, orderId);
    res.json({ paypalOrderId: ppOrder.id, orderNumber: order_number });
  } catch (err) {
    console.error('[checkout/paypal] Fehler:', err);
    res.status(500).json({ error: 'PayPal-Bestellung konnte nicht erstellt werden.' });
  }
});

router.post('/paypal/capture/:paypalOrderId', async (req, res) => {
  if (!paypalLib.enabled) return res.status(503).json({ error: 'PayPal ist derzeit nicht verfügbar.' });
  try {
    const order = db.prepare('SELECT * FROM orders WHERE paypal_order_id = ?').get(req.params.paypalOrderId);
    if (!order) return res.status(404).json({ error: 'Bestellung nicht gefunden.' });
    const capture = await paypalLib.captureOrder(req.params.paypalOrderId);
    const status = capture.status;
    if (status === 'COMPLETED') {
      finalizeOrderPaid(order.id);
      return res.json({ ok: true, orderNumber: order.order_number, status: 'paid' });
    }
    res.status(402).json({ error: `Zahlung nicht abgeschlossen (Status: ${status}).` });
  } catch (err) {
    console.error('[checkout/paypal/capture] Fehler:', err);
    res.status(500).json({ error: 'PayPal-Zahlung konnte nicht bestätigt werden.' });
  }
});

// ---- Rechnung / Überweisung: no live processor, order stays pending until manual reconciliation ----
router.post('/invoice', async (req, res) => {
  const parsed = parseCheckoutBody(req, res);
  if (!parsed) return;
  try {
    const { orderId, order_number } = createPendingOrder({ ...parsed, paymentMethod: 'invoice' });
    const bankHolder = process.env.BANK_HOLDER || '';
    const bankIban = process.env.BANK_IBAN || '';
    const bankBic = process.env.BANK_BIC || '';
    const lines = parsed.lineItems.map((li) => `${li.qty}× ${li.title} — ${fmtEuro(li.unitPriceCents * li.qty)}`).join('\n');
    sendMail({
      to: parsed.customer.email,
      subject: `Bestellung ${order_number} — Zahlung per Überweisung`,
      text: `Hallo ${parsed.customer.firstName},\n\ndanke für deine Bestellung!\n\n${lines}\n\nGesamt: ${fmtEuro(parsed.totalCents)}\nBestellnummer: ${order_number}\n\nBitte überweise den Betrag unter Angabe der Bestellnummer an:\n${bankHolder}\nIBAN: ${bankIban}\nBIC: ${bankBic}\n\nDer Versand erfolgt nach Zahlungseingang.`,
    }).catch((err) => console.error('[checkout/invoice] E-Mail-Versand fehlgeschlagen:', err));
    res.json({ orderNumber: order_number, orderId });
  } catch (err) {
    console.error('[checkout/invoice] Fehler:', err);
    res.status(500).json({ error: 'Bestellung konnte nicht angelegt werden.' });
  }
});

module.exports = router;
