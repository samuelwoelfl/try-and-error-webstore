async function apiRequest(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: options.body ? { 'Content-Type': 'application/json' } : undefined,
    ...options,
  });
  let data = null;
  try { data = await res.json(); } catch (e) { /* no body */ }
  if (!res.ok) {
    const message = (data && data.error) || `Fehler (${res.status})`;
    throw new Error(message);
  }
  return data;
}

const api = {
  works: () => apiRequest('/api/works.php'),
  work: (id) => apiRequest(`/api/work.php?id=${encodeURIComponent(id)}`),
  settings: () => apiRequest('/api/settings.php'),
  config: () => apiRequest('/api/config.php'),
  order: (orderNumber) => apiRequest(`/api/order.php?number=${encodeURIComponent(orderNumber)}`),
  contact: (body) => apiRequest('/api/contact.php', { method: 'POST', body: JSON.stringify(body) }),

  checkoutStripeIntent: (body) => apiRequest('/api/checkout/stripe-intent.php', { method: 'POST', body: JSON.stringify(body) }),
  checkoutStripeSync: (paymentIntentId) => apiRequest(`/api/checkout/stripe-sync.php?id=${encodeURIComponent(paymentIntentId)}`, { method: 'POST' }),
  checkoutPaypalCreate: (body) => apiRequest('/api/checkout/paypal-create.php', { method: 'POST', body: JSON.stringify(body) }),
  checkoutPaypalCapture: (paypalOrderId) => apiRequest(`/api/checkout/paypal-capture.php?id=${encodeURIComponent(paypalOrderId)}`, { method: 'POST' }),
  checkoutInvoice: (body) => apiRequest('/api/checkout/invoice.php', { method: 'POST', body: JSON.stringify(body) }),

  adminLogin: (body) => apiRequest('/api/admin/login.php', { method: 'POST', body: JSON.stringify(body) }),
  adminLogout: () => apiRequest('/api/admin/logout.php', { method: 'POST' }),
  adminSession: () => apiRequest('/api/admin/session.php'),
  adminWorks: () => apiRequest('/api/admin/works.php'),
  adminCreateWork: (body) => apiRequest('/api/admin/works.php', { method: 'POST', body: JSON.stringify(body) }),
  adminUpdateWork: (id, body) => apiRequest(`/api/admin/work.php?id=${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify(body) }),
  adminToggleStatus: (id) => apiRequest(`/api/admin/work-status.php?id=${encodeURIComponent(id)}`, { method: 'PATCH' }),
  adminToggleVisibility: (id) => apiRequest(`/api/admin/work-visibility.php?id=${encodeURIComponent(id)}`, { method: 'PATCH' }),
  adminDeleteWork: (id) => apiRequest(`/api/admin/work.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' }),
  adminSettings: () => apiRequest('/api/admin/settings.php'),
  adminUpdateSettings: (body) => apiRequest('/api/admin/settings.php', { method: 'PUT', body: JSON.stringify(body) }),
  adminOrders: () => apiRequest('/api/admin/orders.php'),
  adminCancelOrder: (id) => apiRequest(`/api/admin/order-status.php?id=${encodeURIComponent(id)}`, { method: 'PATCH' }),
  adminDeleteOrder: (id) => apiRequest(`/api/admin/order.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' }),
  adminToggleOrderFulfilled: (id) => apiRequest(`/api/admin/order-fulfillment.php?id=${encodeURIComponent(id)}`, { method: 'PATCH' }),
  adminToggleOrderPaid: (id) => apiRequest(`/api/admin/order-payment.php?id=${encodeURIComponent(id)}`, { method: 'PATCH' }),
  adminUpload: (file) => {
    const fd = new FormData();
    fd.append('image', file);
    return fetch('/api/admin/upload.php', { method: 'POST', credentials: 'same-origin', body: fd }).then(async (res) => {
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Upload fehlgeschlagen.');
      return data;
    });
  },
};
