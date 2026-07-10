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
  works: () => apiRequest('/api/works'),
  work: (id) => apiRequest(`/api/works/${id}`),
  settings: () => apiRequest('/api/settings'),
  config: () => apiRequest('/api/config'),
  order: (orderNumber) => apiRequest(`/api/orders/${orderNumber}`),
  contact: (body) => apiRequest('/api/contact', { method: 'POST', body: JSON.stringify(body) }),

  checkoutStripeIntent: (body) => apiRequest('/api/checkout/stripe/intent', { method: 'POST', body: JSON.stringify(body) }),
  checkoutStripeSync: (paymentIntentId) => apiRequest(`/api/checkout/stripe/sync/${paymentIntentId}`, { method: 'POST' }),
  checkoutPaypalCreate: (body) => apiRequest('/api/checkout/paypal/create-order', { method: 'POST', body: JSON.stringify(body) }),
  checkoutPaypalCapture: (paypalOrderId) => apiRequest(`/api/checkout/paypal/capture/${paypalOrderId}`, { method: 'POST' }),
  checkoutInvoice: (body) => apiRequest('/api/checkout/invoice', { method: 'POST', body: JSON.stringify(body) }),

  adminLogin: (body) => apiRequest('/api/admin/login', { method: 'POST', body: JSON.stringify(body) }),
  adminLogout: () => apiRequest('/api/admin/logout', { method: 'POST' }),
  adminSession: () => apiRequest('/api/admin/session'),
  adminWorks: () => apiRequest('/api/admin/works'),
  adminCreateWork: (body) => apiRequest('/api/admin/works', { method: 'POST', body: JSON.stringify(body) }),
  adminUpdateWork: (id, body) => apiRequest(`/api/admin/works/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  adminToggleStatus: (id) => apiRequest(`/api/admin/works/${id}/status`, { method: 'PATCH' }),
  adminDeleteWork: (id) => apiRequest(`/api/admin/works/${id}`, { method: 'DELETE' }),
  adminSettings: () => apiRequest('/api/admin/settings'),
  adminUpdateSettings: (body) => apiRequest('/api/admin/settings', { method: 'PUT', body: JSON.stringify(body) }),
  adminUpload: (file) => {
    const fd = new FormData();
    fd.append('image', file);
    return fetch('/api/admin/upload', { method: 'POST', credentials: 'same-origin', body: fd }).then(async (res) => {
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Upload fehlgeschlagen.');
      return data;
    });
  },
};
