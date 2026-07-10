function cartItemHtml(work, qty) {
  const img = work.imageUrl
    ? `<img src="${escapeHtml(work.imageUrl)}" alt="${escapeHtml(work.title)}">`
    : '';
  const stepper = work.kind === 'edition' ? `
    <div class="qty-stepper" data-work-id="${work.id}">
      <button type="button" data-action="dec" aria-label="Weniger">−</button>
      <span>${qty}</span>
      <button type="button" data-action="inc" aria-label="Mehr">+</button>
    </div>` : '';
  return `
    <div class="cart-item">
      <div class="cart-item__thumb${work.imageUrl ? '' : ' stripe-placeholder'}">${img}</div>
      <div class="cart-item__body">
        <h3 class="cart-item__title">${escapeHtml(work.title)}</h3>
        <p class="cart-item__meta">${escapeHtml(metaLineFor(work))}</p>
        <button type="button" class="cart-item__remove" data-remove="${work.id}">Entfernen</button>
      </div>
      ${stepper}
      <div class="cart-item__line">${fmtEuro(work.priceCents * qty)}</div>
    </div>`;
}

async function initCartPage() {
  const root = document.getElementById('cart-root');
  const entries = Cart.asEntries();
  if (entries.length === 0) {
    root.innerHTML = `
      <div class="cart-empty">
        <p>Dein Warenkorb ist noch leer.</p>
        <a href="/#werke" class="btn btn--primary">Werke entdecken</a>
      </div>`;
    return;
  }

  let works;
  try {
    works = await api.works();
  } catch (e) {
    root.innerHTML = '<p class="center-note">Warenkorb konnte nicht geladen werden.</p>';
    return;
  }
  const byId = new Map(works.map((w) => [w.id, w]));

  const validEntries = entries.filter((e) => byId.has(e.workId) && byId.get(e.workId).status !== 'verkauft');
  if (validEntries.length !== entries.length) {
    // Prune references to works that were removed or sold out since being added.
    const cart = {};
    validEntries.forEach((e) => { cart[e.workId] = e.qty; });
    Cart.write(cart);
  }
  if (validEntries.length === 0) {
    root.innerHTML = `
      <div class="cart-empty">
        <p>Dein Warenkorb ist noch leer.</p>
        <a href="/#werke" class="btn btn--primary">Werke entdecken</a>
      </div>`;
    return;
  }

  const total = validEntries.reduce((sum, e) => sum + byId.get(e.workId).priceCents * e.qty, 0);

  root.innerHTML = `
    <div class="cart-list">
      ${validEntries.map((e) => cartItemHtml(byId.get(e.workId), e.qty)).join('')}
    </div>
    <div class="cart-summary-wrap">
      <div class="cart-summary">
        <div class="cart-summary__row"><span>Zwischensumme</span><span>${fmtEuro(total)}</span></div>
        <div class="cart-summary__row cart-summary__row--border"><span>Versand &amp; Verpackung</span><span>inklusive</span></div>
        <div class="cart-summary__total"><span>Gesamt</span><span>${fmtEuro(total)}</span></div>
        <a href="/kasse.html" class="btn btn--primary btn--block">Zur Kasse</a>
        <a href="/#werke" class="cart-summary__browse">weiter stöbern</a>
      </div>
    </div>`;

  root.querySelectorAll('[data-remove]').forEach((btn) => {
    btn.addEventListener('click', () => {
      Cart.remove(btn.dataset.remove);
      initCartPage();
    });
  });
  root.querySelectorAll('.qty-stepper').forEach((stepper) => {
    const workId = Number(stepper.dataset.workId);
    const work = byId.get(workId);
    stepper.querySelector('[data-action="inc"]').addEventListener('click', () => {
      const cart = Cart.read();
      Cart.setQty(workId, (cart[String(workId)] || 0) + 1, 99);
      initCartPage();
    });
    stepper.querySelector('[data-action="dec"]').addEventListener('click', () => {
      const cart = Cart.read();
      Cart.setQty(workId, (cart[String(workId)] || 0) - 1, 99);
      initCartPage();
    });
  });
}

document.addEventListener('DOMContentLoaded', initCartPage);
