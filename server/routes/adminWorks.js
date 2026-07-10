const express = require('express');
const db = require('../db');
const requireAdmin = require('../middleware/requireAdmin');
const { serializeWork } = require('../lib/serialize');

const router = express.Router();
router.use(requireAdmin);

function toRecord(body) {
  const kind = body.kind === 'edition' ? 'edition' : 'unique';
  const status = body.status === 'verkauft' ? 'verkauft' : 'verfuegbar';
  return {
    title: String(body.title || 'Ohne Titel').trim(),
    technique: String(body.technique || '').trim(),
    width_cm: Number(body.widthCm) || 0,
    height_cm: Number(body.heightCm) || 0,
    year: Number(body.year) || new Date().getFullYear(),
    price_cents: Math.round(Number(body.priceEuro) * 100) || 0,
    kind,
    edition_label: kind === 'edition' ? String(body.editionLabel || 'Edition').trim() : '',
    status,
    description: String(body.description || '').trim(),
    image_url: body.imageUrl || null,
  };
}

router.get('/', (req, res) => {
  const rows = db.prepare('SELECT * FROM works ORDER BY sort_order ASC, id ASC').all();
  res.json(rows.map(serializeWork));
});

router.post('/', (req, res) => {
  const rec = toRecord(req.body || {});
  const maxSort = db.prepare('SELECT COALESCE(MAX(sort_order), -1) AS m FROM works').get().m;
  const info = db.prepare(`
    INSERT INTO works (title, technique, width_cm, height_cm, year, price_cents, kind, edition_label, status, description, image_url, sort_order)
    VALUES (@title, @technique, @width_cm, @height_cm, @year, @price_cents, @kind, @edition_label, @status, @description, @image_url, @sort_order)
  `).run({ ...rec, sort_order: maxSort + 1 });
  const row = db.prepare('SELECT * FROM works WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json(serializeWork(row));
});

router.put('/:id', (req, res) => {
  const existing = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  if (!existing) return res.status(404).json({ error: 'Werk nicht gefunden.' });
  const rec = toRecord(req.body || {});
  if (!req.body.imageUrl) rec.image_url = existing.image_url;
  db.prepare(`
    UPDATE works SET title=@title, technique=@technique, width_cm=@width_cm, height_cm=@height_cm,
      year=@year, price_cents=@price_cents, kind=@kind, edition_label=@edition_label, status=@status,
      description=@description, image_url=@image_url
    WHERE id=@id
  `).run({ ...rec, id: req.params.id });
  const row = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  res.json(serializeWork(row));
});

router.patch('/:id/status', (req, res) => {
  const existing = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  if (!existing) return res.status(404).json({ error: 'Werk nicht gefunden.' });
  const next = existing.status === 'verkauft' ? 'verfuegbar' : 'verkauft';
  db.prepare('UPDATE works SET status = ? WHERE id = ?').run(next, req.params.id);
  const row = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  res.json(serializeWork(row));
});

router.delete('/:id', (req, res) => {
  const existing = db.prepare('SELECT * FROM works WHERE id = ?').get(req.params.id);
  if (!existing) return res.status(404).json({ error: 'Werk nicht gefunden.' });
  try {
    db.prepare('DELETE FROM works WHERE id = ?').run(req.params.id);
    res.json({ ok: true });
  } catch (err) {
    if (String(err.message).includes('FOREIGN KEY')) {
      return res.status(409).json({ error: 'Werk ist Teil bestehender Bestellungen und kann nicht gelöscht werden — stattdessen auf „Verkauft" setzen.' });
    }
    throw err;
  }
});

module.exports = router;
