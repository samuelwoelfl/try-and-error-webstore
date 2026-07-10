const path = require('path');
const fs = require('fs');
const bcrypt = require('bcryptjs');
const Database = require('better-sqlite3');

const DATA_DIR = path.join(__dirname, '..', 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

const db = new Database(path.join(DATA_DIR, 'store.sqlite3'));
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

db.exec(`
CREATE TABLE IF NOT EXISTS works (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  technique TEXT NOT NULL DEFAULT '',
  width_cm INTEGER NOT NULL DEFAULT 0,
  height_cm INTEGER NOT NULL DEFAULT 0,
  year INTEGER NOT NULL DEFAULT 0,
  price_cents INTEGER NOT NULL DEFAULT 0,
  kind TEXT NOT NULL DEFAULT 'unique' CHECK (kind IN ('unique','edition')),
  edition_label TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'verfuegbar' CHECK (status IN ('verfuegbar','verkauft')),
  description TEXT NOT NULL DEFAULT '',
  image_url TEXT,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  hero_title TEXT NOT NULL DEFAULT '',
  hero_sub TEXT NOT NULL DEFAULT '',
  about_title TEXT NOT NULL DEFAULT '',
  about_text TEXT NOT NULL DEFAULT '',
  logo_url TEXT
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_number TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','paid','failed','cancelled')),
  payment_method TEXT NOT NULL CHECK (payment_method IN ('stripe','paypal','invoice')),
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  email TEXT NOT NULL,
  street TEXT NOT NULL,
  zip TEXT NOT NULL,
  city TEXT NOT NULL,
  total_cents INTEGER NOT NULL,
  currency TEXT NOT NULL DEFAULT 'eur',
  stripe_payment_intent_id TEXT,
  paypal_order_id TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  paid_at TEXT
);

CREATE TABLE IF NOT EXISTS order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  work_id INTEGER NOT NULL REFERENCES works(id),
  title TEXT NOT NULL,
  meta_line TEXT NOT NULL DEFAULT '',
  unit_price_cents INTEGER NOT NULL,
  qty INTEGER NOT NULL,
  kind TEXT NOT NULL
);
`);

function seedWorksIfEmpty() {
  const count = db.prepare('SELECT COUNT(*) AS n FROM works').get().n;
  if (count > 0) return;
  const insert = db.prepare(`
    INSERT INTO works (title, technique, width_cm, height_cm, year, price_cents, kind, edition_label, status, description, sort_order)
    VALUES (@title, @technique, @width_cm, @height_cm, @year, @price_cents, @kind, @edition_label, @status, @description, @sort_order)
  `);
  const seed = [
    { title: 'Morgennebel', technique: 'Öl auf Leinwand', width_cm: 80, height_cm: 100, year: 2024, price_cents: 145000, kind: 'unique', edition_label: '', status: 'verfuegbar', description: 'Ein weiches Landschaftsmotiv im ersten Licht des Tages. Lasierende Ölschichten lassen den Nebel über dem Feld beinahe schweben.' },
    { title: 'Küstenlicht', technique: 'Acryl auf Leinwand', width_cm: 60, height_cm: 80, year: 2023, price_cents: 98000, kind: 'unique', edition_label: '', status: 'verkauft', description: 'Kräftige Acrylflächen fangen das flirrende Licht über dem Wasser ein — eines meiner ausdrucksstärksten Werke.' },
    { title: 'Stille', technique: 'Aquarell auf Papier', width_cm: 30, height_cm: 40, year: 2024, price_cents: 32000, kind: 'unique', edition_label: '', status: 'verfuegbar', description: 'Eine zarte, meditative Studie in gedeckten Tönen. Gerahmt hinter entspiegeltem Museumsglas.' },
    { title: 'Feldstudie', technique: 'Kunstdruck (Fine Art)', width_cm: 50, height_cm: 70, year: 2024, price_cents: 12000, kind: 'edition', edition_label: 'Edition von 50', status: 'verfuegbar', description: 'Hochwertiger Fine-Art-Print auf Hahnemühle-Papier, handsigniert und nummeriert. Ohne Rahmen.' },
    { title: 'Abendrot', technique: 'Öl auf Leinwand', width_cm: 100, height_cm: 120, year: 2022, price_cents: 198000, kind: 'unique', edition_label: '', status: 'verfuegbar', description: 'Ein großformatiges Statement in warmen Rot- und Ockertönen — das Herzstück meiner Landschaftsserie.' },
    { title: 'Fragment', technique: 'Mischtechnik auf Holz', width_cm: 40, height_cm: 40, year: 2024, price_cents: 54000, kind: 'unique', edition_label: '', status: 'verfuegbar', description: 'Collagierte Papiere, Kreide und Öl auf Holztafel. Ein taktiles, kleines Unikat.' },
    { title: 'Horizont', technique: 'Kunstdruck (Fine Art)', width_cm: 40, height_cm: 50, year: 2023, price_cents: 9500, kind: 'edition', edition_label: 'Edition von 100', status: 'verfuegbar', description: 'Ruhiger Farbverlauf als handsignierter Fine-Art-Print. Passt in jeden Standardrahmen.' },
  ];
  const insertMany = db.transaction((rows) => {
    rows.forEach((r, i) => insert.run({ ...r, sort_order: i }));
  });
  insertMany(seed);
}

function seedSettingsIfEmpty() {
  const row = db.prepare('SELECT id FROM settings WHERE id = 1').get();
  if (row) return;
  db.prepare(`
    INSERT INTO settings (id, hero_title, hero_sub, about_title, about_text, logo_url)
    VALUES (1, @hero_title, @hero_sub, @about_title, @about_text, NULL)
  `).run({
    hero_title: 'Malerei aus dem Atelier.',
    hero_sub: 'Handgemalte Unikate und limitierte Editionen — direkt aus dem Studio zu dir nach Hause. Jedes Werk erzählt eine eigene, stille Geschichte.',
    about_title: 'Try & Error',
    about_text: 'Try & Error ist ein offenes Atelier-Projekt aus Stuttgart.\n\nHier entstehen handgemalte Unikate und kleine Editionen — meist in Öl und Acryl, manchmal in Aquarell. Das Projekt kreist um Licht, Landschaft und stille Momente und versteht das Ausprobieren als festen Teil der Arbeit.\n\nJedes Original ist ein Unikat und wird sorgfältig verpackt versendet. Editionen entstehen als kleine, limitierte Auflagen. Bei Fragen zu einem Werk schreib uns gern.',
  });
}

function seedAdminIfEmpty() {
  const count = db.prepare('SELECT COUNT(*) AS n FROM admin_users').get().n;
  if (count > 0) return;
  const email = process.env.ADMIN_EMAIL || 'admin@atelier.de';
  const password = process.env.ADMIN_PASSWORD || 'change-me-now';
  const hash = bcrypt.hashSync(password, 10);
  db.prepare('INSERT INTO admin_users (email, password_hash) VALUES (?, ?)').run(email.toLowerCase(), hash);
  if (!process.env.ADMIN_PASSWORD) {
    console.warn(`\n[admin] Kein ADMIN_PASSWORD in .env gesetzt — Fallback-Login: ${email} / ${password}\n[admin] Bitte in .env ändern und Server neu starten, damit ein neues Passwort gesetzt wird (Tabelle admin_users vorher leeren).\n`);
  }
}

seedWorksIfEmpty();
seedSettingsIfEmpty();
seedAdminIfEmpty();

module.exports = db;
