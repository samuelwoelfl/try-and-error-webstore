const express = require('express');
const db = require('../db');
const { serializeOrder } = require('../lib/serialize');

const router = express.Router();

// Public lookup by order number (used on the confirmation page) — deliberately
// exposes only what's needed to show a receipt, not the full customer record beyond
// what the buyer already knows from having just placed the order.
router.get('/:orderNumber', (req, res) => {
  const row = db.prepare('SELECT * FROM orders WHERE order_number = ?').get(req.params.orderNumber);
  if (!row) return res.status(404).json({ error: 'Bestellung nicht gefunden.' });
  const items = db.prepare('SELECT * FROM order_items WHERE order_id = ?').all(row.id);
  res.json(serializeOrder(row, items));
});

module.exports = router;
