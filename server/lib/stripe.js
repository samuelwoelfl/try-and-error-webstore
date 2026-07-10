const secretKey = process.env.STRIPE_SECRET_KEY || '';
const enabled = secretKey.startsWith('sk_');

let stripe = null;
if (enabled) {
  stripe = require('stripe')(secretKey);
}

module.exports = {
  enabled,
  stripe,
  publishableKey: process.env.STRIPE_PUBLISHABLE_KEY || '',
  webhookSecret: process.env.STRIPE_WEBHOOK_SECRET || '',
};
