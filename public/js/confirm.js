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
    const [order, config] = await Promise.all([api.order(orderNumber), api.config()]);
    root.innerHTML = confirmHtml(order, config.invoice);
  } catch (e) {
    root.innerHTML = '<p class="center-note">Bestellung wurde nicht gefunden.</p>';
  }
}

document.addEventListener('DOMContentLoaded', initConfirm);
