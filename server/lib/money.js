function fmtEuro(cents) {
  return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function orderNumber() {
  const n = Math.floor(100000 + Math.random() * 900000);
  return `MV-${n}`;
}

module.exports = { fmtEuro, orderNumber };
