const express = require('express');
const db = require('../db');
const { serializeSettings } = require('../lib/serialize');

const router = express.Router();

router.get('/', (req, res) => {
  const row = db.prepare('SELECT * FROM settings WHERE id = 1').get();
  res.json(serializeSettings(row));
});

module.exports = router;
