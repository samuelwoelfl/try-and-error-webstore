const express = require('express');
const db = require('../db');
const requireAdmin = require('../middleware/requireAdmin');
const { serializeSettings } = require('../lib/serialize');

const router = express.Router();
router.use(requireAdmin);

router.get('/', (req, res) => {
  const row = db.prepare('SELECT * FROM settings WHERE id = 1').get();
  res.json(serializeSettings(row));
});

router.put('/', (req, res) => {
  const b = req.body || {};
  const current = db.prepare('SELECT * FROM settings WHERE id = 1').get();
  db.prepare(`
    UPDATE settings SET hero_title=@hero_title, hero_sub=@hero_sub, about_title=@about_title,
      about_text=@about_text, logo_url=@logo_url
    WHERE id = 1
  `).run({
    hero_title: b.heroTitle ?? current.hero_title,
    hero_sub: b.heroSub ?? current.hero_sub,
    about_title: b.aboutTitle ?? current.about_title,
    about_text: b.aboutText ?? current.about_text,
    logo_url: b.logoUrl !== undefined ? (b.logoUrl || null) : current.logo_url,
  });
  const row = db.prepare('SELECT * FROM settings WHERE id = 1').get();
  res.json(serializeSettings(row));
});

module.exports = router;
