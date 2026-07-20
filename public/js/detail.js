function detailHtml(w) {
  const altText = w.description ? `${w.title} – ${w.description}` : w.title;
  const img = w.imageUrl
    ? `<img src="${escapeHtml(w.imageUrl)}" alt="${escapeHtml(altText)}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0">`
    : `<span class="stripe-placeholder__label">Werkabbildung</span>`;
  const soldOverlay = w.status === 'verkauft' ? `<div class="sold-overlay"><span>Verkauft</span></div>` : '';
  const editionLine = w.kind === 'edition'
    ? `<div class="detail__edition-label">${escapeHtml(w.editionLabel)}</div>` : '';
  const subMeta = w.kind === 'unique' ? 'Unikat · signiert' : `${w.editionLabel} · handsigniert & nummeriert`;
  const available = w.status !== 'verkauft';
  const statusColor = available ? 'var(--accent)' : 'var(--text-tertiary)';

  const actions = available ? `
    <div class="detail__actions">
      <button class="btn btn--primary" id="buy-now">Jetzt kaufen</button>
      <button class="btn btn--outline" id="add-cart">In den Warenkorb</button>
    </div>
    <p class="detail__incart" id="incart-note" hidden></p>
  ` : `
    <a href="/kontakt.html" class="btn btn--outline btn--block">Ähnliches Werk anfragen</a>
  `;

  return `
    <div class="detail-grid">
      <div class="detail-grid__img stripe-placeholder">${img}${soldOverlay}</div>
      <div class="sticky-panel">
        ${editionLine}
        <h1 class="heading detail__title">${escapeHtml(w.title)}</h1>
        <p class="detail__submeta">${escapeHtml(subMeta)}</p>
        <div class="detail__price">${fmtEuro(w.priceCents)}</div>
        <dl class="detail__dl">
          <div class="detail__dl-row"><dt>Technik</dt><dd>${escapeHtml(w.technique)}</dd></div>
          <div class="detail__dl-row"><dt>Maße</dt><dd>${w.widthCm} × ${w.heightCm} cm</dd></div>
          <div class="detail__dl-row"><dt>Jahr</dt><dd>${w.year}</dd></div>
          <div class="detail__dl-row"><dt>Verfügbarkeit</dt><dd style="color:${statusColor}">${available ? 'Verfügbar' : 'Verkauft'}</dd></div>
        </dl>
        <p class="detail__desc">${escapeHtml(w.description)}</p>
        ${actions}
      </div>
    </div>`;
}

function updateHeadForWork(w) {
  const url = `https://try-and-error.art/werk.html?id=${w.id}`;
  const description = w.description || `${w.title} — Try & Error`;

  document.querySelector('meta[name="description"]')?.setAttribute('content', description);
  document.getElementById('canonical-link')?.setAttribute('href', url);
  document.getElementById('og-title')?.setAttribute('content', `${w.title} — Try & Error`);
  document.getElementById('og-description')?.setAttribute('content', description);
  document.getElementById('og-url')?.setAttribute('content', url);

  const ld = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: w.title,
    description: w.description || undefined,
    image: w.imageUrl || undefined,
    url,
    offers: {
      '@type': 'Offer',
      priceCurrency: 'EUR',
      price: (w.priceCents / 100).toFixed(2),
      availability: w.status === 'verkauft'
        ? 'https://schema.org/SoldOut'
        : 'https://schema.org/InStock',
      url,
    },
  };
  let script = document.getElementById('ld-product');
  if (!script) {
    script = document.createElement('script');
    script.type = 'application/ld+json';
    script.id = 'ld-product';
    document.head.appendChild(script);
  }
  script.textContent = JSON.stringify(ld);
}

function updateInCartNote(work) {
  const note = document.getElementById('incart-note');
  if (!note) return;
  const cart = Cart.read();
  const qty = cart[String(work.id)] || 0;
  if (qty > 0) {
    note.hidden = false;
    note.textContent = `✓ ${qty > 1 ? qty + '×' : '1×'} im Warenkorb`;
  } else {
    note.hidden = true;
  }
}

async function initDetail() {
  const root = document.getElementById('detail-root');
  const params = new URLSearchParams(location.search);
  const id = params.get('id');
  if (!id) {
    root.innerHTML = '<p class="center-note">Kein Werk angegeben.</p>';
    return;
  }
  let work;
  try {
    work = await api.work(id);
  } catch (e) {
    root.innerHTML = '<p class="center-note">Werk wurde nicht gefunden.</p>';
    return;
  }

  document.title = `${work.title} — Try & Error`;
  updateHeadForWork(work);
  root.innerHTML = detailHtml(work);

  const maxQty = work.kind === 'unique' ? 1 : 99;
  const addBtn = document.getElementById('add-cart');
  const buyBtn = document.getElementById('buy-now');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      Cart.add(work.id, maxQty);
      updateInCartNote(work);
    });
  }
  if (buyBtn) {
    buyBtn.addEventListener('click', () => {
      Cart.add(work.id, maxQty);
      location.href = '/kasse.html';
    });
  }
  updateInCartNote(work);
}

document.addEventListener('DOMContentLoaded', initDetail);
