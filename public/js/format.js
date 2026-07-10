function fmtEuro(cents) {
  return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function metaLineFor(work) {
  return work.kind === 'unique'
    ? `${work.technique} · ${work.widthCm} × ${work.heightCm} cm`
    : `${work.editionLabel} · ${work.technique}`;
}

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}
