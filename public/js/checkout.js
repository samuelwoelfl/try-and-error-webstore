function loadScript(src) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

const CTA_LABELS = { stripe: 'Jetzt bezahlen', paypal: 'Mit PayPal bezahlen', invoice: 'Kauf abschließen' };

const checkoutState = {
  lineItems: [],
  totalCents: 0,
  method: null,
  config: null,
  stripe: null,
  elements: null,
  paypalRendered: false,
};

function readCustomer() {
  const form = document.getElementById('checkout-form');
  const fd = new FormData(form);
  return {
    firstName: fd.get('firstName')?.trim(),
    lastName: fd.get('lastName')?.trim(),
    email: fd.get('email')?.trim(),
    street: fd.get('street')?.trim(),
    zip: fd.get('zip')?.trim(),
    city: fd.get('city')?.trim(),
  };
}

function validateCustomerClientSide(c) {
  if (!c.firstName || !c.lastName || !c.email || !c.street || !c.zip || !c.city) {
    return 'Bitte alle Kontakt- und Lieferdaten ausfüllen.';
  }
  if (!/^\S+@\S+\.\S+$/.test(c.email)) return 'Bitte eine gültige E-Mail-Adresse angeben.';
  return null;
}

function showFormError(msg) {
  const el = document.getElementById('form-error');
  if (!msg) { el.hidden = true; el.textContent = ''; return; }
  el.hidden = false;
  el.textContent = msg;
  el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function renderSummary() {
  const itemsEl = document.getElementById('summary-items');
  itemsEl.innerHTML = checkoutState.lineItems.map((li) => `
    <div class="order-summary__row">
      <span>${escapeHtml(li.title)} ${li.kind === 'edition' ? `<span class="order-summary__row-qty">× ${li.qty}</span>` : ''}</span>
      <span>${fmtEuro(li.unitPriceCents * li.qty)}</span>
    </div>`).join('');
  document.getElementById('summary-total').textContent = fmtEuro(checkoutState.totalCents);
}

function paymentOptionHtml(id, label, hint, tag, enabled) {
  const on = checkoutState.method === id;
  return `
    <div class="pay-option${on ? ' pay-option--on' : ''}${enabled ? '' : ' pay-option--disabled'}" data-method="${id}" style="${enabled ? '' : 'opacity:.5;cursor:not-allowed'}">
      <span class="pay-option__radio">${on ? '<span class="pay-option__radio-dot"></span>' : ''}</span>
      <div class="pay-option__body">
        <div class="pay-option__name">${label}</div>
        <div class="pay-option__hint">${hint}${enabled ? '' : ' · derzeit nicht konfiguriert'}</div>
      </div>
      <span class="pay-option__tag">${tag}</span>
    </div>`;
}

function renderPaymentOptions() {
  const cfg = checkoutState.config;
  const container = document.getElementById('payment-options');
  container.innerHTML = [
    paymentOptionHtml('invoice', 'Rechnung / Überweisung', 'Versand nach Zahlungseingang', 'SEPA', cfg.invoice.enabled),
    paymentOptionHtml('stripe', 'Kredit-/Debitkarte', 'Visa · Mastercard · Apple&nbsp;Pay · Google&nbsp;Pay', 'STRIPE', cfg.stripe.enabled),
  ].join('');
  // PayPal is temporarily hidden from checkout (business decision, not a removal) —
  // mountPaypalButtons()/paypal-capture.php etc. are left intact so it can be
  // re-added to this list later without any backend changes.
  container.querySelectorAll('[data-method]').forEach((el) => {
    el.addEventListener('click', () => {
      const method = el.dataset.method;
      const enabledMap = { stripe: cfg.stripe.enabled, invoice: cfg.invoice.enabled };
      if (!enabledMap[method]) return;
      selectMethod(method);
    });
  });
}

async function selectMethod(method) {
  checkoutState.method = method;
  renderPaymentOptions();
  document.getElementById('pay-cta').textContent = CTA_LABELS[method];

  document.getElementById('stripe-payment-element').hidden = method !== 'stripe';
  document.getElementById('paypal-button-container').hidden = method !== 'paypal';
  document.getElementById('invoice-hint').hidden = method !== 'invoice';
  document.getElementById('pay-cta').hidden = method === 'paypal';

  if (method === 'stripe') await mountStripeElement();
  if (method === 'paypal') await mountPaypalButtons();
}

async function mountStripeElement() {
  if (checkoutState.elements) return; // already mounted
  const cfg = checkoutState.config;
  if (!cfg.stripe.enabled || !window.Stripe) return;
  checkoutState.stripe = window.Stripe(cfg.stripe.publishableKey);
  checkoutState.elements = checkoutState.stripe.elements({
    mode: 'payment',
    amount: checkoutState.totalCents,
    currency: 'eur',
    appearance: {
      variables: {
        colorPrimary: '#000000',
        colorText: '#221f1c',
        fontFamily: "'Jost', system-ui, sans-serif",
        borderRadius: '2px',
      },
    },
  });
  const paymentElement = checkoutState.elements.create('payment');
  paymentElement.mount('#stripe-payment-element');
}

async function mountPaypalButtons() {
  const cfg = checkoutState.config;
  if (!cfg.paypal.enabled) return;
  if (!window.paypal) {
    await loadScript(`https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(cfg.paypal.clientId)}&currency=EUR`);
  }
  if (checkoutState.paypalRendered) return;
  checkoutState.paypalRendered = true;
  window.paypal.Buttons({
    style: { color: 'black', shape: 'rect', label: 'pay' },
    createOrder: async () => {
      showFormError(null);
      const customer = readCustomer();
      const err = validateCustomerClientSide(customer);
      if (err) { showFormError(err); throw new Error(err); }
      const res = await api.checkoutPaypalCreate({ cart: checkoutState.lineItems.map((li) => ({ workId: li.workId, qty: li.qty })), customer });
      return res.paypalOrderId;
    },
    onApprove: async (data) => {
      try {
        const res = await api.checkoutPaypalCapture(data.orderID);
        Cart.clear();
        location.href = `/bestellung.html?order=${encodeURIComponent(res.orderNumber)}`;
      } catch (e) {
        showFormError(e.message);
      }
    },
    onError: (err) => {
      showFormError('PayPal-Zahlung konnte nicht abgeschlossen werden.');
      console.error(err);
    },
  }).render('#paypal-button-container');
}

async function handleSubmit(evt) {
  evt.preventDefault();
  showFormError(null);
  const customer = readCustomer();
  const err = validateCustomerClientSide(customer);
  if (err) return showFormError(err);

  const cta = document.getElementById('pay-cta');
  cta.disabled = true;

  try {
    if (checkoutState.method === 'stripe') {
      const { error: submitErr } = await checkoutState.elements.submit();
      if (submitErr) throw new Error(submitErr.message);

      const cart = checkoutState.lineItems.map((li) => ({ workId: li.workId, qty: li.qty }));
      const intentRes = await api.checkoutStripeIntent({ cart, customer });

      const { error, paymentIntent } = await checkoutState.stripe.confirmPayment({
        elements: checkoutState.elements,
        clientSecret: intentRes.clientSecret,
        confirmParams: {
          return_url: `${location.origin}/bestellung.html?order=${encodeURIComponent(intentRes.orderNumber)}`,
          payment_method_data: { billing_details: { name: `${customer.firstName} ${customer.lastName}`, email: customer.email } },
        },
        redirect: 'if_required',
      });
      if (error) throw new Error(error.message);

      if (paymentIntent && paymentIntent.status === 'succeeded') {
        await api.checkoutStripeSync(paymentIntent.id).catch(() => {});
      }
      Cart.clear();
      location.href = `/bestellung.html?order=${encodeURIComponent(intentRes.orderNumber)}`;
    } else if (checkoutState.method === 'invoice') {
      const cart = checkoutState.lineItems.map((li) => ({ workId: li.workId, qty: li.qty }));
      const res = await api.checkoutInvoice({ cart, customer });
      Cart.clear();
      location.href = `/bestellung.html?order=${encodeURIComponent(res.orderNumber)}`;
    }
  } catch (e) {
    showFormError(e.message || 'Zahlung fehlgeschlagen. Bitte versuche es erneut.');
  } finally {
    cta.disabled = false;
  }
}

async function initCheckout() {
  const entries = Cart.asEntries();
  if (entries.length === 0) {
    document.getElementById('checkout-empty').hidden = false;
    document.getElementById('checkout-form').hidden = true;
    return;
  }

  const [works, config] = await Promise.all([api.works(), api.config()]);
  checkoutState.config = config;
  const byId = new Map(works.map((w) => [w.id, w]));

  checkoutState.lineItems = entries
    .filter((e) => byId.has(e.workId) && byId.get(e.workId).status !== 'verkauft')
    .map((e) => {
      const w = byId.get(e.workId);
      return { workId: w.id, title: w.title, unitPriceCents: w.priceCents, qty: e.qty, kind: w.kind };
    });

  if (checkoutState.lineItems.length === 0) {
    document.getElementById('checkout-empty').hidden = false;
    document.getElementById('checkout-form').hidden = true;
    return;
  }

  checkoutState.totalCents = checkoutState.lineItems.reduce((sum, li) => sum + li.unitPriceCents * li.qty, 0);
  renderSummary();
  renderPaymentOptions();

  const firstEnabled = ['invoice', 'stripe'].find((m) => config[m].enabled);
  if (firstEnabled) await selectMethod(firstEnabled);

  document.getElementById('checkout-form').addEventListener('submit', handleSubmit);
}

document.addEventListener('DOMContentLoaded', initCheckout);
