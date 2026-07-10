const express = require('express');
const bcrypt = require('bcryptjs');
const db = require('../db');

const router = express.Router();

router.post('/login', (req, res) => {
  const { email, password } = req.body || {};
  if (!email || !password) return res.status(400).json({ error: 'E-Mail und Passwort erforderlich.' });
  const admin = db.prepare('SELECT * FROM admin_users WHERE email = ?').get(String(email).toLowerCase());
  if (!admin || !bcrypt.compareSync(password, admin.password_hash)) {
    return res.status(401).json({ error: 'E-Mail oder Passwort ist falsch.' });
  }
  req.session.adminId = admin.id;
  req.session.adminEmail = admin.email;
  res.json({ ok: true, email: admin.email });
});

router.post('/logout', (req, res) => {
  req.session.destroy(() => res.json({ ok: true }));
});

router.get('/session', (req, res) => {
  if (req.session && req.session.adminId) {
    return res.json({ authed: true, email: req.session.adminEmail });
  }
  res.json({ authed: false });
});

module.exports = router;
