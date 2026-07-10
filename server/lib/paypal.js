const clientId = process.env.PAYPAL_CLIENT_ID || '';
const clientSecret = process.env.PAYPAL_CLIENT_SECRET || '';
const env = (process.env.PAYPAL_ENV || 'sandbox').toLowerCase();
const enabled = Boolean(clientId && clientSecret);

const base = env === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

let cachedToken = null;
let cachedTokenExpiry = 0;

async function getAccessToken() {
  if (cachedToken && Date.now() < cachedTokenExpiry - 30000) return cachedToken;
  const auth = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const res = await fetch(`${base}/v1/oauth2/token`, {
    method: 'POST',
    headers: {
      Authorization: `Basic ${auth}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'grant_type=client_credentials',
  });
  if (!res.ok) throw new Error(`PayPal OAuth fehlgeschlagen (${res.status})`);
  const data = await res.json();
  cachedToken = data.access_token;
  cachedTokenExpiry = Date.now() + data.expires_in * 1000;
  return cachedToken;
}

async function createOrder({ amountCents, currency = 'EUR', orderNumber }) {
  const token = await getAccessToken();
  const res = await fetch(`${base}/v2/checkout/orders`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      intent: 'CAPTURE',
      purchase_units: [
        {
          reference_id: orderNumber,
          amount: {
            currency_code: currency,
            value: (amountCents / 100).toFixed(2),
          },
        },
      ],
    }),
  });
  if (!res.ok) throw new Error(`PayPal create-order fehlgeschlagen (${res.status}): ${await res.text()}`);
  return res.json();
}

async function captureOrder(paypalOrderId) {
  const token = await getAccessToken();
  const res = await fetch(`${base}/v2/checkout/orders/${paypalOrderId}/capture`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });
  const data = await res.json();
  if (!res.ok) throw new Error(`PayPal capture fehlgeschlagen (${res.status}): ${JSON.stringify(data)}`);
  return data;
}

module.exports = { enabled, clientId, createOrder, captureOrder };
