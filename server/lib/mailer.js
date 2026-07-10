const nodemailer = require('nodemailer');

const enabled = Boolean(process.env.SMTP_HOST && process.env.SMTP_USER && process.env.SMTP_PASS);

let transporter = null;
if (enabled) {
  transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: Number(process.env.SMTP_PORT || 587),
    secure: Number(process.env.SMTP_PORT) === 465,
    auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
  });
}

async function sendMail({ to, subject, text, html }) {
  if (!enabled) {
    console.log(`[mailer] SMTP nicht konfiguriert — E-Mail an ${to} nur geloggt.\nBetreff: ${subject}\n${text}`);
    return { skipped: true };
  }
  return transporter.sendMail({
    from: process.env.SMTP_FROM || process.env.SMTP_USER,
    to,
    subject,
    text,
    html,
  });
}

module.exports = { enabled, sendMail };
