const express = require('express');
const db = require('../db');
const stripeLib = require('../lib/stripe');
const { finalizeOrderPaid } = require('../lib/finalizeOrder');

const router = express.Router();

// Mounted with express.raw() body parsing (see server/index.js) — Stripe's signature
// verification needs the exact raw request bytes, not the JSON-parsed body.
router.post('/', (req, res) => {
  if (!stripeLib.enabled) return res.status(503).end();

  let event;
  try {
    if (stripeLib.webhookSecret) {
      const sig = req.headers['stripe-signature'];
      event = stripeLib.stripe.webhooks.constructEvent(req.body, sig, stripeLib.webhookSecret);
    } else {
      // No webhook secret configured (e.g. local dev without `stripe listen`) — trust the payload as-is.
      event = JSON.parse(req.body.toString('utf8'));
    }
  } catch (err) {
    console.error('[webhooks/stripe] Signaturprüfung fehlgeschlagen:', err.message);
    return res.status(400).send(`Webhook Error: ${err.message}`);
  }

  if (event.type === 'payment_intent.succeeded') {
    const intent = event.data.object;
    const order = db.prepare('SELECT * FROM orders WHERE stripe_payment_intent_id = ?').get(intent.id);
    if (order) finalizeOrderPaid(order.id);
  }

  res.json({ received: true });
});

module.exports = router;
