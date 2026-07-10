const CART_KEY = 'tae_cart_v1';

const Cart = {
  read() {
    try {
      const raw = localStorage.getItem(CART_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  },
  write(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    document.dispatchEvent(new CustomEvent('cart:changed', { detail: cart }));
  },
  count() {
    const cart = this.read();
    return Object.values(cart).reduce((a, b) => a + b, 0);
  },
  add(workId, maxQty = 99) {
    const cart = this.read();
    const id = String(workId);
    cart[id] = Math.min((cart[id] || 0) + 1, maxQty);
    this.write(cart);
    return cart[id];
  },
  setQty(workId, qty, maxQty = 99) {
    const cart = this.read();
    const id = String(workId);
    if (qty <= 0) delete cart[id];
    else cart[id] = Math.min(qty, maxQty);
    this.write(cart);
  },
  remove(workId) {
    const cart = this.read();
    delete cart[String(workId)];
    this.write(cart);
  },
  clear() {
    this.write({});
  },
  asEntries() {
    return Object.entries(this.read()).map(([workId, qty]) => ({ workId: Number(workId), qty }));
  },
};

function renderCartBadge() {
  const badge = document.querySelector('[data-cart-badge]');
  if (!badge) return;
  const count = Cart.count();
  if (count > 0) {
    badge.textContent = String(count);
    badge.hidden = false;
  } else {
    badge.hidden = true;
  }
}

document.addEventListener('DOMContentLoaded', renderCartBadge);
document.addEventListener('cart:changed', renderCartBadge);
