async function applyBranding() {
  try {
    const settings = await api.settings();
    if (!settings.logoUrl) return;
    document.querySelectorAll('[data-wordmark]').forEach((el) => {
      const height = el.dataset.wordmark === 'footer' ? 28 : 32;
      el.innerHTML = `<img src="${escapeHtml(settings.logoUrl)}" alt="Try &amp; Error" style="max-height:${height}px;width:auto">`;
    });
  } catch (e) {
    // Storefront still works with the text wordmark if settings can't be loaded.
  }
}

document.addEventListener('DOMContentLoaded', applyBranding);
