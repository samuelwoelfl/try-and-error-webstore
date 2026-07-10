# Handoff: Kunst-Store — Storefront & Admin Portal

## Overview
"Try & Error" is a two-part e-commerce experience for an independent painter (Atelier Stuttgart) selling original paintings and limited fine-art print editions:

- **Storefront** (`Kunst-Store.dc.html`) — public gallery, work detail pages, cart, checkout, order confirmation, and a contact form.
- **Admin Portal** (`Kunst-Store Admin.dc.html`) — password-gated backoffice to manage the catalog (works/inventory), homepage copy, and site branding (logo).

## About the Design Files
The files in this bundle are **design references built as interactive HTML prototypes** — they demonstrate layout, visual design, copy, and intended behavior/state flow. They are **not production code to copy directly** (no real backend, no persistence, no real payment processing — see below). The task is to **recreate these designs in your target stack** (e.g. React/Next.js + a database + a real payment provider), following your codebase's existing patterns, component library, and conventions. If no stack exists yet, choose the framework best suited to a small e-commerce storefront + admin (e.g. Next.js/Remix + Postgres + Stripe).

## Fidelity
**High-fidelity.** Colors, typography, spacing, copy, and interaction states (hover/active/focus, disabled/sold states, cart badge, form validation styling hooks) are final and should be reproduced pixel-for-pixel. All dynamic behavior in the prototype (routing between views, cart math, form state) is implemented in plain JS/React-like state and should be treated as the functional spec — but replaced with real state/data/session logic server-side.

---

## Screens / Views

### Storefront (`Kunst-Store.dc.html`)
One single-page app with client-side view switching (`view` state: gallery / detail / cart / checkout / confirm / contact). In production this should become real routes:

1. **Gallery (`/`)**
   - Sticky header, 82% opacity blurred background, wordmark left, nav right (Werke / Kontakt / Warenkorb button with live count badge).
   - Hero: two-column grid (1.05fr / .95fr), large serif/sans headline (per font tweak), subhead, two CTA buttons ("Werke ansehen", "Anfrage stellen"), two arched/rounded-top image panels on the right (currently border-radius 120px 120px 0 0 / 150px 150px 0 0, aspect-ratio 3/5).
   - Works grid: responsive CSS grid, 2/3/4 columns configurable via a tweak (galleryColumns), each card = image (aspect 4/5) + title + price + meta line (technique/size or edition info) + "Verkauft" (sold) overlay badge + "Edition" badge.
   - About strip: two-column band with alternate background color, arched portrait placeholder + project statement (heading + 2 paragraphs).

2. **Work Detail (`/werke/:id`)**
   - Two-column: large image left, sticky info panel right: edition/unique label, title, meta, price, definition list (Technik/Maße/Jahr/Verfügbarkeit), description, and either "Jetzt kaufen" + "In den Warenkorb" buttons (available) or a "request similar work" CTA (sold).

3. **Cart (`/warenkorb`)**
   - Empty state with CTA back to gallery, or itemized list (thumbnail, title, meta, remove link, quantity stepper for editions only, line total) + summary block (subtotal, shipping "inklusive", grand total, "Zur Kasse" button).

4. **Checkout (`/kasse`)**
   - Two-column: contact/shipping form (Vorname, Nachname, E-Mail, Straße, PLZ, Ort) + payment method selector (radio-style rows: Kreditkarte/Debitkarte via Stripe with inline card fields when selected, PayPal, Rechnung/Überweisung) + sticky order summary card with line items, total, and a payment-method-aware CTA label ("Jetzt bezahlen" / "Mit PayPal bezahlen" / "Kauf abschließen").
   - **Important**: the card number/expiry/CVC inputs shown here are plain text inputs for prototyping only — see Payment Integration section, this must never be built this way in production.

5. **Order Confirmation (`/bestellung/:id`)**
   - Centered success state: checkmark badge, thank-you copy, order number, payment method + total, CTA back to gallery.

6. **Contact (`/kontakt`)**
   - Simple form (Name, E-Mail, Betreff, Nachricht) → success state on submit.

### Admin Portal (`Kunst-Store Admin.dc.html`)
1. **Login** — centered card, email + password, demo-only ("Anmelden" always succeeds in the prototype). Must become real authentication (see State Management).
2. **Shell** — 250px fixed sidebar (wordmark/logo, nav: Werke / Inhalte, footer links: "Store ansehen ↗" external link + "Abmelden") + scrollable content area. A "flash" success banner appears sticky-top for ~2.2s after any save.
3. **Werke (catalog list)** — table-like grid (thumbnail / title+meta / technique / price / status pill (click to toggle Verfügbar⇄Verkauft) / Bearbeiten+Löschen actions), "+ Neues Werk" button top-right.
4. **Werk-Editor** — image dropzone placeholder (left) + form fields (right): Titel, Art (Unikat/Edition select), Status select, Technik, conditional Auflage field (editions only), Breite/Höhe/Jahr/Preis number inputs, Beschreibung textarea. Speichern/Abbrechen buttons.
5. **Inhalte (site content)**:
   - **Branding · Logo** — a 120×120 image upload slot to replace the "Try & Error" text wordmark on the storefront (see Assets/Branding below).
   - **Startseite · Hero** — edit hero headline + subtext.
   - **Über mich** — edit the about-strip headline + body text.
   - Single "Speichern" button for the whole content page.

---

## Interactions & Behavior
- Cart badge only renders when count > 0; quantity steppers only render for edition works (unique originals cap at qty 1).
- "Jetzt kaufen" on a detail page adds the item then jumps straight to checkout; "In den Warenkorb" just adds and shows an inline confirmation line.
- Checkout CTA label and visible fields change based on selected payment method (card fields only show when "Kredit-/Debitkarte" is selected).
- Placing an order clears the cart, generates a display order number (MV-XXXXXX), and navigates to the confirmation view.
- Admin status pill is directly clickable to toggle Verfügbar/Verkauft without opening the editor.
- All navigation is instant/client-side with a small scroll-to-top and a 0.35–0.5s fade-up entrance animation per view.
- Hover states throughout: buttons darken via a computed hover color (color-mix off the accent), outlined buttons gain a faint fill + accent border, nav/links pick up the accent color.

## State Management
Needed in the real implementation (none of this exists as real persistence in the prototype — it's all in-memory component state that resets on reload):
- **Catalog**: works/products (id, title, technique, width/height cm, year, price, kind: unique|edition, edition label, status: verfügbar|verkauft, description, image(s)) — needs a real DB table + image storage/CDN.
- **Cart**: per-session (guest) cart, keyed by product id → quantity, with edition qty caps at defined print-run size and unique works capped at 1.
- **Orders**: order number, line items, totals, payment method, status, customer contact + shipping address, timestamps.
- **Site content**: hero title/subtitle, about title/text, and now a **logo image URL** — a simple singleton "settings" record.
- **Auth**: real session/auth for the admin login (the prototype's login button always succeeds — replace with real credential check + session cookie/JWT).
- **Design tweaks** (accent color, heading font, background tone, gallery column count, hero visibility) are currently per-viewer editor props — decide whether these become persisted site settings (recommended) or stay build-time constants.

## Branding / Logo Swap
The Admin "Inhalte" page now has a **Branding · Logo** upload slot (120×120, drag-and-drop in the prototype via a placeholder component). In the real build:
- Store the uploaded logo as an image asset (object storage + CDN URL) referenced from the settings record.
- On the storefront, the header (Kunst-Store.dc.html, near the top) and footer (near the bottom) currently render the **text wordmark** "Try & Error" — when a logo image is set, swap this render to an `<img>` (e.g. max-height ~32px header / ~28px footer, preserving aspect ratio) instead of the text; fall back to the text wordmark when no logo is set (this fallback behavior is already implied by the Admin copy: "Ohne Bild wird die Textmarke weiter angezeigt").

## Payment Integration (real providers)
The checkout in the prototype is **visual only** — it never contacts a payment processor and stores no real card data. For production, wire up real providers per selected method:

- **Kredit-/Debitkarte → Stripe**
  - Do **not** collect raw card number/expiry/CVC in your own form fields (the prototype's plain inputs are placeholders for layout only — replace them entirely). Use **Stripe Elements** (or Stripe Checkout / Payment Element) so card data goes straight to Stripe and never touches your server — required for PCI compliance.
  - Recommended flow: create a PaymentIntent server-side once the cart/order total is known → mount the Stripe Payment Element in place of the current card fields → confirm payment client-side → verify success via Stripe webhook before marking the order paid.
  - Apple Pay / Google Pay (both mentioned in the UI copy) come for free through the Stripe Payment Element/Payment Request Button if the storefront is served over HTTPS with domains registered in the Stripe dashboard.
- **PayPal**
  - Use the PayPal JS SDK (Smart Buttons) or PayPal Checkout — create an order server-side, redirect/approve via PayPal's flow, then capture the payment and confirm server-side via webhook before fulfilling.
- **Rechnung / Überweisung (invoice/bank transfer)**
  - No live processor — this is a "pay later" method. On order placement, create the order in a "pending payment" status, generate/send an invoice (with bank details) by email, and reconcile manually or via bank-transfer matching before shipping. Consider a service like Stripe's SEPA-based invoicing or a dedicated German B2C provider (e.g. Klarna "Rechnungskauf", Ratepay) if automated dunning/risk-checking is wanted.
- **General**
  - Move all order-total calculation and final pricing to the server — never trust client-submitted totals.
  - Send the confirmation email server-side after payment is verified via webhook, not immediately on client-side "success".
  - The order number shown in the confirmation view is a random display string in the prototype — replace with a real sequential/UUID order id from your orders table.

## Design Tokens

**Colors**
- Background: white #ffffff (default, "Weiß" tweak) or creme #F9F7F4 ("Creme" tweak)
- Surface/card: #fafafa (white mode) / #ffffff (creme mode)
- Input background: #ffffff (white mode) / #faf8f5 (creme mode)
- Text: primary #221f1c, secondary/muted #4a453f, tertiary/label #8a8075
- Accent (default): #000000 — curated swatch options #000000, #1a1a1a, #3f3f3f, #5a5a5a; hover = color-mix(in srgb, accent 74%, #fff); active = color-mix(in srgb, accent 90%, #fff)
- Borders: rgba(34,31,28,.1) to rgba(34,31,28,.28) depending on emphasis
- Status colors: sold/neutral pill rgba(34,31,28,.07) bg / #8a8075 text; available pill color-mix(in srgb, #1f8a5b 14%, #fff) bg / #1f7a52 text
- Placeholder art tones: #e7e2da / #ddd7cd (creme) or #ededed / #e2e2e2 (white)

**Typography**
- Headings: font tweak — "Spectral" (serif, weight 600), "Hanken Grotesk" (sans, weight 600, current default), or "Jost" (geometric sans) — Google Fonts.
- Body/UI: Jost 300–400.
- Scale: hero H1 clamp(42px,6vw,80px); section H2 clamp(26–34px, 3.2–3.4vw, 40–52px); card titles 17–20px; body copy 15–16px/1.65–1.7 line-height; small caps labels 10.5–13px with letter-spacing .12em–.34em, uppercase.

**Spacing / Layout**
- Content max-width 1360px (storefront) at 90% viewport width; checkout/detail max-width 1080px; cart 900px; contact 640px.
- Grid gaps generally clamp(20–28px, 2.6–4vw, 44–72px).
- Admin sidebar fixed at 250px; content padding 38px 40px.

**Shape**
- Buttons/inputs: border-radius 2px (sharp, minimal).
- Cards/panels: border-radius 4–6px.
- Decorative image panels: arched top via asymmetric border-radius (e.g. 120px 120px 0 0).
- Status pills / cart badge: fully rounded (border-radius 20px / 50%).

**Motion**
- Entrance: fadeUp keyframes (translateY 8–10px → 0, opacity 0→1), 0.35–0.5s ease, on every view change.
- Interactive: background/border-color transitions 0.12–0.2s; buttons translateY(1px) on :active.

## Assets
- Two uploaded photographic images used in the hero (uploads/pasted-1783373999988-0.png, uploads/pasted-1783374008858-0.png) — replace with real product/portrait photography.
- All other imagery in the prototype (work thumbnails, detail images, about portrait) is a **diagonal-stripe placeholder pattern** — real photography of each artwork is required before launch.
- No logo image exists yet — the Admin "Branding · Logo" upload is new UI added to support one; until supplied, storefront keeps rendering the text wordmark "Try & Error".
- Google Fonts: Spectral, Hanken Grotesk, Jost (all loaded via `<link>` in both files' `<head>`).

## Files
- `Kunst-Store.dc.html` — storefront design (all views, in-file logic/state).
- `Kunst-Store Admin.dc.html` — admin portal design (all views, in-file logic/state).
- `image-slot.js` — placeholder drag-and-drop image component used for the logo upload UI in the admin file (prototyping aid only; replace with a real file-upload → storage flow in production).
