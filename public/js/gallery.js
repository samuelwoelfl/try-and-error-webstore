function workCardHtml(w) {
  const altText = w.description ? `${w.title} – ${w.description}` : w.title;
  const img = w.imageUrl
    ? `<img src="${escapeHtml(w.imageUrl)}" alt="${escapeHtml(altText)}" style="width:100%;height:100%;object-fit:cover">`
    : `<div class="stripe-placeholder" style="position:absolute;inset:0"><span class="stripe-placeholder__label">Werkabbildung</span></div>`;
  const soldOverlay = w.status === 'verkauft'
    ? `<div class="sold-overlay"><span>Verkauft</span></div>` : '';
  const editionBadge = w.kind === 'edition'
    ? `<span class="badge-edition">Edition</span>` : '';
  return `
    <a class="work-card" href="/werk.html?id=${w.id}">
      <div class="work-card__img">${img}${soldOverlay}${editionBadge}</div>
      <div class="work-card__row">
        <h3 class="work-card__title">${escapeHtml(w.title)}</h3>
        <span class="work-card__price">${fmtEuro(w.priceCents)}</span>
      </div>
      <p class="work-card__meta">${escapeHtml(metaLineFor(w))}</p>
    </a>`;
}

async function initGallery() {
  const grid = document.getElementById('works-grid');
  const countEl = document.getElementById('work-count');
  let workCount = null;
  try {
    const works = await api.works();
    grid.innerHTML = works.map(workCardHtml).join('');
    workCount = works.length;
  } catch (e) {
    grid.innerHTML = `<p class="center-note">Werke konnten nicht geladen werden.</p>`;
  }

  try {
    const settings = await api.settings();
    if (settings.heroEyebrow) {
      document.getElementById('hero-eyebrow').textContent = settings.heroEyebrow;
    }
    if (settings.heroTitle) {
      document.getElementById('hero-title').textContent = settings.heroTitle;
    }
    if (settings.heroSub) {
      document.getElementById('hero-sub').textContent = settings.heroSub;
    }
    if (settings.heroImageAUrl) {
      document.getElementById('hero-img-a').src = settings.heroImageAUrl;
    }
    if (settings.heroImageBUrl) {
      document.getElementById('hero-img-b').src = settings.heroImageBUrl;
    }
    if (settings.worksEyebrow) {
      document.getElementById('works-eyebrow').textContent = settings.worksEyebrow;
    }
    if (settings.worksTitle) {
      document.getElementById('works-title').textContent = settings.worksTitle;
    }
    if (workCount !== null) {
      countEl.textContent = `${workCount} ${settings.worksCountLabel || 'Arbeiten'}`;
    }
    if (settings.aboutEyebrow) {
      document.getElementById('about-eyebrow').textContent = settings.aboutEyebrow;
    }
    if (settings.aboutTitle) {
      document.getElementById('about-title').textContent = settings.aboutTitle;
    }
    if (settings.aboutText) {
      const paragraphs = settings.aboutText.split(/\n\s*\n/).filter(Boolean);
      document.getElementById('about-text').innerHTML = paragraphs
        .map((p) => `<p class="about-strip__text">${escapeHtml(p).replace(/\n/g, '<br>')}</p>`)
        .join('');
    }
    if (settings.aboutImageUrl) {
      const portrait = document.getElementById('about-portrait');
      portrait.classList.remove('stripe-placeholder');
      portrait.innerHTML = `<img src="${escapeHtml(settings.aboutImageUrl)}" alt="${escapeHtml(settings.aboutTitle || 'Über das Projekt')}">`;
    }
  } catch (e) {
    // Falls back to the static defaults already in the markup.
    if (workCount !== null) {
      countEl.textContent = `${workCount} Arbeiten`;
    }
  }
}

document.addEventListener('DOMContentLoaded', initGallery);
