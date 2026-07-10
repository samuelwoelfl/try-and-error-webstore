# Try & Error — Kunst-Store

Öffentlicher Storefront (Galerie, Werk-Detail, Warenkorb, Kasse, Bestellbestätigung, Kontakt) plus passwortgeschütztes Admin-Portal (Werke-Verwaltung, Seiteninhalte, Logo-Upload) für ein Atelier in Stuttgart.

Umgesetzt nach der Spezifikation in [`design_handoff_kunst_store/README.md`](design_handoff_kunst_store/README.md).

## Tech-Stack

Bewusst leicht gehalten, kein Build-Schritt im Frontend:

- **Backend:** Node.js + Express
- **DB:** SQLite (`better-sqlite3`), Datei unter `data/store.sqlite3`
- **Auth:** Sessions (`express-session`) + `bcryptjs` für das Admin-Passwort
- **Uploads:** `multer`, Dateien liegen unter `uploads/` und werden unter `/uploads/…` ausgeliefert
- **Frontend:** statisches HTML/CSS/Vanilla-JS unter `public/` (echte Seiten/Routen statt SPA)
- **Zahlungen:** Stripe (Payment Element: Karte, Apple Pay, Google Pay), PayPal (Orders v2 REST), Rechnung/Überweisung

## Setup

```bash
npm install
cp .env.example .env
# .env ausfüllen (siehe unten)
npm run dev     # Server mit Auto-Reload auf http://localhost:3000
# oder: npm start
```

Beim ersten Start legt der Server automatisch die SQLite-Tabellen an, befüllt den Katalog mit den sieben Beispielwerken aus der Design-Referenz und legt einen Admin-Account aus `ADMIN_EMAIL` / `ADMIN_PASSWORD` an. Admin-Login: [http://localhost:3000/admin/login.html](http://localhost:3000/admin/login.html).

**Wichtig:** Der Admin-Account wird nur beim allerersten Start (leere `admin_users`-Tabelle) angelegt. Um das Passwort später zu ändern, entweder die Zeile in `admin_users` löschen und Server neu starten, oder eine eigene Passwort-Reset-Route ergänzen.

## Umgebungsvariablen (`.env`)

Siehe [`.env.example`](.env.example) für die vollständige Liste. Kurzüberblick:

| Variable | Zweck |
|---|---|
| `SESSION_SECRET` | Signierschlüssel für Admin-Sessions — in Produktion zwingend auf einen langen Zufallswert setzen |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Zugangsdaten für den einmalig geseedeten Admin-Account |
| `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` | Stripe-Testkeys aus dem [Dashboard](https://dashboard.stripe.com/test/apikeys) |
| `STRIPE_WEBHOOK_SECRET` | Signing-Secret für den Webhook (siehe unten) |
| `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` / `PAYPAL_ENV` | Sandbox- oder Live-App aus dem [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/applications) |
| `BANK_HOLDER` / `BANK_IBAN` / `BANK_BIC` | Bankverbindung, die bei „Rechnung/Überweisung“ per E-Mail verschickt wird |
| `SMTP_*` / `CONTACT_EMAIL` | Für echten E-Mail-Versand (Bestellbestätigung, Rechnung, Kontaktformular) — ohne SMTP-Konfiguration werden E-Mails nur in die Konsole geloggt, der Rest funktioniert trotzdem |

Jede Zahlungsmethode ist **einzeln optional**: Fehlen die Stripe- bzw. PayPal-Keys, blendet die Kasse die jeweilige Option automatisch als „derzeit nicht konfiguriert“ aus, statt zu crashen. „Rechnung/Überweisung“ braucht keinen externen Anbieter und funktioniert immer.

## Zahlungsanbindung — Details

- **Stripe:** Serverseitig wird pro Bestellung ein `PaymentIntent` über den tatsächlich in der DB berechneten Betrag erstellt (niemals der vom Client gesendete Betrag). Im Frontend läuft das Stripe **Payment Element** (`stripe.elements({mode:'payment', ...})`), Kartendaten gehen direkt an Stripe. Apple Pay/Google Pay erscheinen automatisch, sobald die Seite per HTTPS mit einer bei Stripe verifizierten Domain läuft. Finalisiert wird die Bestellung serverseitig über den Webhook `POST /api/webhooks/stripe` (Event `payment_intent.succeeded`) — für lokale Tests:
  ```bash
  stripe listen --forward-to localhost:3000/api/webhooks/stripe
  ```
  Ohne laufenden `stripe listen` synchronisiert das Frontend den Zahlungsstatus nach erfolgreichem `confirmPayment()` zusätzlich über einen Fallback-Endpunkt (`/api/checkout/stripe/sync/:id`) — rein für die lokale Entwicklung, in Produktion ist der Webhook die verbindliche Quelle.
- **PayPal:** Server erstellt eine PayPal-Order (Orders v2 REST, OAuth Client-Credentials) parallel zur eigenen `pending`-Order, das PayPal-JS-SDK rendert die echten Smart-Buttons in der Kasse, Capture + Freigabe passiert serverseitig (`/api/checkout/paypal/capture/:id`).
- **Rechnung/Überweisung:** Kein Zahlungsanbieter — die Bestellung wird sofort mit Status `pending` angelegt, Kunde erhält die Bankdaten per E-Mail, der Admin markiert das Werk nach Zahlungseingang manuell als „Verkauft“ (Status-Pille in der Werke-Liste).

## Bekannte Einschränkungen (bewusste Scope-Entscheidungen)

- **Session-Store:** Express-Sessions liegen im Prozessspeicher (Standard-`MemoryStore`) — passend für einen einzelnen Node-Prozess; für Multi-Instance-Deployments einen externen Store (Redis o.ä.) ergänzen.
- **Bild-Storage:** Uploads liegen lokal unter `uploads/`, nicht auf einem CDN/Objektspeicher — für Produktion z. B. auf S3 + CloudFront umstellen.
- **Editionen ohne Stück-Ledger:** Editionswerke haben ein Label („Edition von 50“), aber keinen serverseitig gezählten Bestand — nur Unikate werden nach erfolgreicher Zahlung automatisch als „Verkauft“ markiert.
- **Design-Tweaks** (Akzentfarbe, Kopfzeilen-Schrift, Spaltenzahl, Hero ein/aus) aus dem Prototyp sind hier bewusst **Build-Time-Konstanten** in `public/css/tokens.css`, nicht admin-editierbar — das Admin-Portal deckt exakt das ab, was die Design-Referenz zeigt (Werke + Inhalte inkl. Logo).

## Projektstruktur

```
server/           Express-App, Routen, DB, Zahlungs-/Mail-Adapter
public/           Storefront + Admin (statisches HTML/CSS/JS)
public/admin/     Admin-Seiten (Login, Werke, Editor, Inhalte)
data/             SQLite-Datei (gitignored, wird automatisch angelegt)
uploads/          Hochgeladene Werk-/Logo-Bilder (gitignored, wird automatisch angelegt)
design_handoff_kunst_store/   Ursprüngliche Design-Spezifikation (Referenz, kein Produktionscode)
```
