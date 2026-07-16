function fmtEuro(cents) {
  return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function fmtDateTime(isoLike) {
  const d = new Date(isoLike.replace(' ', 'T'));
  if (isNaN(d)) return isoLike;
  return d.toLocaleDateString('de-DE') + ' · ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
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
