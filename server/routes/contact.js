const express = require('express');
const { sendMail } = require('../lib/mailer');

const router = express.Router();

router.post('/', async (req, res) => {
  const { name, email, subject, message } = req.body || {};
  if (!name || !email || !message) {
    return res.status(400).json({ error: 'Name, E-Mail und Nachricht sind erforderlich.' });
  }
  const to = process.env.CONTACT_EMAIL || process.env.SMTP_FROM || process.env.SMTP_USER;
  try {
    if (to) {
      await sendMail({
        to,
        subject: `Kontaktformular: ${subject || 'Neue Nachricht'}`,
        text: `Von: ${name} <${email}>\n\n${message}`,
      });
    } else {
      console.log(`[contact] Neue Nachricht von ${name} <${email}>: ${subject || ''}\n${message}`);
    }
    res.json({ ok: true });
  } catch (err) {
    console.error('[contact] Fehler beim Versand:', err);
    res.status(500).json({ error: 'Nachricht konnte nicht gesendet werden.' });
  }
});

module.exports = router;
