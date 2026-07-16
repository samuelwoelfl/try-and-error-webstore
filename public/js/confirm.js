const PAY_LABELS = { stripe: 'Kredit-/Debitkarte', paypal: 'PayPal', invoice: 'Rechnung / Überweisung' };

function confirmHtml(order, bank) {
  const pendingNote = (order.status === 'pending' && order.paymentMethod === 'invoice')
    ? `<p class="confirm-pending-note">Bitte überweise den Betrag unter Angabe der Bestellnummer an:<br>
        ${escapeHtml(bank.bankHolder || '(Bankverbindung folgt per E-Mail)')}${bank.bankIban ? `<br>IBAN: ${escapeHtml(bank.bankIban)}` : ''}${bank.bankBic ? ` · BIC: ${escapeHtml(bank.bankBic)}` : ''}</p>`
    : (order.status === 'pending'
      ? `<p class="confirm-pending-note">Deine Zahlung wird noch bestätigt — du erhältst gleich eine E-Mail.</p>`
      : '');

  return `
    <div class="confirm-check">✓</div>
    <h1 class="heading confirm-title">Vielen Dank!</h1>
    <p class="confirm-lead">Deine Bestellung ist eingegangen. Eine Bestätigung mit allen Details ist unterwegs zu dir.</p>
    <p class="confirm-label">Bestellnummer</p>
    <p class="confirm-order-no">${escapeHtml(order.orderNumber)}</p>
    <p class="confirm-meta">Zahlung: ${PAY_LABELS[order.paymentMethod] || order.paymentMethod} · Gesamt ${fmtEuro(order.totalCents)}</p>
    ${pendingNote}
    <a href="/" class="btn btn--primary">Zurück zur Galerie</a>`;
}

async function initConfirm() {
  const root = document.getElementById('confirm-root');
  const params = new URLSearchParams(location.search);
  const orderNumber = params.get('order');
  if (!orderNumber) {
    root.innerHTML = '<p class="center-note">Keine Bestellung angegeben.</p>';
    return;
  }
  try {
    let [order, config] = await Promise.all([api.order(orderNumber), api.config()]);

    // Redirect-based Stripe methods (Klarna, Bancontact, EPS, ...) land here straight
    // from the payment provider, before any webhook has necessarily arrived — Stripe
    // appends payment_intent to the return_url on the way back, so this re-checks the
    // status directly instead of only waiting on the webhook (which occasionally gets
    // blocked by the host's security layer). Idempotent and safe to call redundantly.
    const paymentIntentId = params.get('payment_intent');
    if (order.status === 'pending' && order.paymentMethod === 'stripe' && paymentIntentId) {
      try {
        await api.checkoutStripeSync(paymentIntentId);
        order = await api.order(orderNumber);
      } catch (e) {
        // Falls back to showing the pending state below — the webhook may still land.
      }
    }

    root.innerHTML = confirmHtml(order, config.invoice);
  } catch (e) {
    root.innerHTML = '<p class="center-note">Bestellung wurde nicht gefunden.</p>';
  }
}

document.addEventListener('DOMContentLoaded', initConfirm);
