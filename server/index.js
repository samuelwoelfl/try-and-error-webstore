require('dotenv').config();
const path = require('path');
const express = require('express');
const session = require('express-session');

require('./db'); // ensures schema + seed run before anything else

const app = express();
const PORT = Number(process.env.PORT || 3000);
const isProd = process.env.NODE_ENV === 'production';

app.set('trust proxy', 1);

// Stripe webhook needs the raw request body for signature verification, so it must be
// registered before the global express.json() body parser touches the request.
app.use('/api/webhooks/stripe', express.raw({ type: 'application/json' }), require('./routes/webhooksStripe'));

app.use(express.json());
app.use(
  session({
    name: 'atelier.sid',
    secret: process.env.SESSION_SECRET || 'dev-only-insecure-secret-change-me',
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      secure: isProd,
      maxAge: 1000 * 60 * 60 * 12,
    },
  })
);

app.use('/uploads', express.static(path.join(__dirname, '..', 'uploads')));
app.use(express.static(path.join(__dirname, '..', 'public')));

app.use('/api/works', require('./routes/works'));
app.use('/api/settings', require('./routes/settings'));
app.use('/api/config', require('./routes/config'));
app.use('/api/contact', require('./routes/contact'));
app.use('/api/orders', require('./routes/orders'));
app.use('/api/checkout', require('./routes/checkout'));

app.use('/api/admin', require('./routes/adminAuth'));
app.use('/api/admin/works', require('./routes/adminWorks'));
app.use('/api/admin/settings', require('./routes/adminSettings'));
app.use('/api/admin/upload', require('./routes/adminUpload'));

app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ error: 'Interner Serverfehler.' });
});

app.listen(PORT, () => {
  console.log(`Try & Error läuft auf http://localhost:${PORT}`);
});
