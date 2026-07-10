# Try & Error — Kunst-Store (PHP-Version)

Öffentlicher Storefront (Galerie, Werk-Detail, Warenkorb, Kasse, Bestellbestätigung, Kontakt) plus passwortgeschütztes Admin-Portal (Werke-Verwaltung, Seiteninhalte, Logo-Upload) für ein Atelier in Stuttgart.

Umgesetzt nach der Spezifikation in [`design_handoff_kunst_store/README.md`](design_handoff_kunst_store/README.md). Diese Branch-Version ist bewusst auf **klassisches PHP-Shared-Hosting** (z. B. Strato Web-Hosting-Paket) zugeschnitten — kein Node.js, kein Composer, kein Build-Schritt. Alles läuft, sobald die Dateien per FTP hochgeladen sind.

## Tech-Stack

- **Backend:** PHP 8.1+ (pure PHP, keine Frameworks/Composer-Pakete — läuft auf jedem Standard-PHP-Hosting)
- **DB:** MySQL/MariaDB via PDO
- **Auth:** native PHP-Sessions + `password_hash()`/`password_verify()`
- **Uploads:** `move_uploaded_file()`, Dateien landen direkt im Webspace unter `public/uploads/`
- **Frontend:** statisches HTML/CSS/Vanilla-JS unter `public/` (unverändert gegenüber der Node-Version — nur der Backend-Unterbau wurde getauscht)
- **Zahlungen:** Stripe und PayPal werden direkt per cURL gegen die REST-APIs angesprochen (keine SDKs) — dadurch keine Composer-Abhängigkeit

## Verzeichnisstruktur — wichtig für den Upload

```
public/        ← Web-Root. NUR der INHALT dieses Ordners kommt in dein htdocs/-Verzeichnis.
inc/           ← PHP-Includes (DB, Auth, Stripe/PayPal-Clients, Mailer).
.env           ← Zugangsdaten (DB, Stripe, PayPal, SMTP).
```

**Bevorzugt:** `inc/` und `.env` liegen **außerhalb** von `htdocs/` (als Geschwisterordner/-datei in deinem FTP-Home). Dann sind sie über HTTP grundsätzlich unerreichbar, unabhängig von jeglicher Serverkonfiguration.

**Falls dein FTP-Zugang auf `htdocs/` eingesperrt ist** (bei manchen Strato-Paketen ist das der Fall — man kommt im FTP-Client nicht „eine Ebene höher"): dann lade `inc/` und `.env` einfach **direkt in `htdocs/`** hoch, genau neben die übrigen Dateien. Das ist genauso sicher, weil:
- `inc/` seine eigene `inc/.htaccess` mitbringt (`Require all denied`) — blockiert jeden direkten HTTP-Zugriff auf alles darin, egal wo der Ordner physisch liegt
- `.env` durch die `<FilesMatch "^\.">`-Regel in `public/.htaccess` abgedeckt ist (blockiert alle Dotfiles)

Beide Varianten sind also unterstützt — nimm einfach die, die dein FTP-Zugang zulässt.

**Konkret für Strato (freier FTP-Zugriff bis übers Home-Verzeichnis):**
```
dein-ftp-home/
├── htdocs/              ← Inhalt von public/ hierhin hochladen
│   ├── index.html
│   ├── api/
│   ├── css/ js/ img/ admin/
│   └── uploads/         ← muss beschreibbar sein (Standard bei Strato)
├── inc/                 ← Ordner inc/ genau so hierhin hochladen
└── .env                 ← aus .env.example kopieren und ausfüllen, hierhin hochladen
```

**Konkret für Strato (FTP-Zugriff auf `htdocs/` beschränkt):**
```
htdocs/
├── index.html
├── api/
├── css/ js/ img/ admin/
├── uploads/
├── inc/                 ← Ordner inc/ hier HINEIN hochladen
└── .env                 ← .env hier HINEIN hochladen
```

## Setup

### Lokal testen

```bash
# .env im Projekt-Root anlegen (Repo-Root = Geschwister von public/, wie oben beschrieben)
cp .env.example .env
# .env ausfüllen — für lokale Tests reicht eine lokale MySQL-Instanz

php -S localhost:8000 -t public
```

Kein `npm install`, kein `composer install` — die App braucht nur die PHP-Extensions, die praktisch jedes Hosting mitbringt: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `session`, `openssl`, `json`.

Beim ersten Request legt die App automatisch die Tabellen an, befüllt den Katalog mit den sieben Beispielwerken aus der Design-Referenz und legt den Admin-Account aus `ADMIN_EMAIL`/`ADMIN_PASSWORD` an (Marker-Datei `.installed` neben `.env` verhindert, dass das bei jedem Request erneut geprüft wird). Admin-Login: `/admin/login.html`.

**Passwort später ändern:** Zeile in der `admin_users`-Tabelle löschen **und** die `.installed`-Datei entfernen, dann einmal die Seite neu laden — die App seedet dann mit dem aktuellen `.env`-Wert neu.

### Bei Strato deployen

1. Im Strato-Kundenlogin unter „Datenbanken" eine MySQL-Datenbank anlegen → Host/Name/Nutzer/Passwort notieren
2. `.env` aus `.env.example` lokal ausfüllen (DB-Zugangsdaten + Stripe/PayPal/Bank/Mail)
3. Per FTP hochladen: Inhalt von `public/` → `htdocs/`, Ordner `inc/` und Datei `.env` entweder als Geschwister von `htdocs/` oder — falls dein FTP-Zugang nicht höher als `htdocs/` kommt — direkt hinein in `htdocs/` (siehe beide Varianten oben)
4. Domain aufrufen — fertig, kein weiterer Schritt nötig
5. Stripe-Webhook im Dashboard auf `https://deine-domain.de/api/webhooks/stripe.php` setzen

## Umgebungsvariablen (`.env`)

Siehe [`.env.example`](.env.example) für die vollständige Liste. Jede Zahlungsmethode ist einzeln optional — fehlen Stripe- bzw. PayPal-Keys, blendet die Kasse die jeweilige Option automatisch als „derzeit nicht konfiguriert" aus. „Rechnung/Überweisung" braucht keinen externen Anbieter.

| Variable | Zweck |
|---|---|
| `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS` | MySQL-Zugangsdaten aus dem Strato-Kundenlogin |
| `ADMIN_EMAIL`/`ADMIN_PASSWORD` | Zugangsdaten für den einmalig geseedeten Admin-Account |
| `STRIPE_SECRET_KEY`/`STRIPE_PUBLISHABLE_KEY`/`STRIPE_WEBHOOK_SECRET` | Aus dem [Stripe-Dashboard](https://dashboard.stripe.com/test/apikeys) |
| `PAYPAL_CLIENT_ID`/`PAYPAL_CLIENT_SECRET`/`PAYPAL_ENV` | Aus dem [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/applications) |
| `BANK_HOLDER`/`BANK_IBAN`/`BANK_BIC` | Bankverbindung für „Rechnung/Überweisung"-E-Mails |
| `MAIL_ENABLED`/`MAIL_FROM`/`CONTACT_EMAIL` | E-Mail-Versand über PHPs eingebaute `mail()`-Funktion |

## Zahlungsanbindung — Details

- **Stripe:** Serverseitig wird pro Bestellung ein `PaymentIntent` über den tatsächlich in der DB berechneten Betrag erstellt (niemals der vom Client gesendete Betrag) — via direktem cURL-Request an `api.stripe.com`, kein SDK nötig. Im Frontend läuft das Stripe **Payment Element**. Finalisiert wird die Bestellung über den Webhook `public/api/webhooks/stripe.php` (Event `payment_intent.succeeded`), inklusive manueller Signaturprüfung nach Stripes dokumentiertem HMAC-Verfahren. Ein Fallback-Sync-Endpoint (`checkout/stripe-sync.php`) hilft beim lokalen Testen ohne konfigurierten Webhook.
- **PayPal:** Server erstellt eine PayPal-Order (Orders v2 REST, OAuth Client-Credentials, ebenfalls reines cURL) parallel zur eigenen `pending`-Order. Das PayPal-JS-SDK rendert die echten Smart-Buttons in der Kasse, Capture passiert serverseitig.
- **Rechnung/Überweisung:** Bestellung wird sofort mit Status `pending` angelegt, Kunde erhält die Bankdaten per E-Mail, der Admin markiert das Werk nach Zahlungseingang manuell als „Verkauft".

## E-Mail-Versand

Nutzt PHPs eingebaute `mail()`-Funktion — auf den meisten Shared-Hosting-Paketen (inkl. Strato) für die eigene Domain ohne weitere Konfiguration lauffähig. Für zuverlässigere Zustellung (SPF/DKIM, Bounce-Handling) kann später ein SMTP-Relay über eine Bibliothek wie PHPMailer ergänzt werden — das ist bewusst nicht Teil dieses schlanken Set-ups, um ohne Composer auszukommen.

## Bekannte Einschränkungen (bewusste Scope-Entscheidungen)

- **Editionen ohne Stück-Ledger:** Editionswerke haben ein Label („Edition von 50"), aber keinen serverseitig gezählten Bestand — nur Unikate werden nach erfolgreicher Zahlung automatisch als „Verkauft" markiert.
- **Design-Tweaks** (Akzentfarbe, Kopfzeilen-Schrift, Spaltenzahl, Hero ein/aus) sind Build-Time-Konstanten in `public/css/tokens.css`, nicht admin-editierbar — das Admin-Portal deckt exakt das ab, was die Design-Referenz zeigt (Werke + Inhalte inkl. Logo).
- **Kein Objektspeicher/CDN:** Uploads liegen lokal im Webspace unter `public/uploads/` — für sehr hohe Traffic-Anforderungen ließe sich das später auf ein CDN umstellen.

## Projektstruktur

```
inc/                            PHP-Includes: DB, Auth, Serializer, Stripe/PayPal-Clients, Mailer
  .htaccess                     Blockiert jeden direkten HTTP-Zugriff auf diesen Ordner
public/                         Web-Root
  api/                          JSON-Endpunkte (Storefront + Admin + Checkout + Webhook)
  admin/                        Admin-Seiten (Login, Werke, Editor, Inhalte)
  css/ js/ img/                 Storefront-Assets
  uploads/                      Hochgeladene Werk-/Logo-Bilder (gitignored bis auf .gitkeep)
  *.html                        Storefront-Seiten (Galerie, Detail, Warenkorb, Kasse, Bestätigung, Kontakt)
design_handoff_kunst_store/     Ursprüngliche Design-Spezifikation (Referenz, kein Produktionscode)
```
