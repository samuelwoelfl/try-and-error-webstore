const express = require('express');
const requireAdmin = require('../middleware/requireAdmin');
const { upload } = require('../lib/upload');

const router = express.Router();

router.post('/', requireAdmin, (req, res) => {
  upload.single('image')(req, res, (err) => {
    if (err) return res.status(400).json({ error: err.message });
    if (!req.file) return res.status(400).json({ error: 'Keine Datei erhalten.' });
    res.json({ url: `/uploads/${req.file.filename}` });
  });
});

module.exports = router;
