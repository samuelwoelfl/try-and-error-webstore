const express = require('express');
const db = require('../db');
const { serializeWork } = require('../lib/serialize');

const router = express.Router();

router.get('/', (req, res) => {
  const rows = db.prepare('SELECT * FROM works ORDER BY sort_order ASC, id ASC').all();
  res.json(rows.map(serializeWork));
});

router.get('/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Werk nicht gefunden.' });
  res.json(serializeWork(row));
});

module.exports = router;
