const express = require('express');
const stripeLib = require('../lib/stripe');
const paypalLib = require('../lib/paypal');

const router = express.Router();

router.get('/', (req, res) => {
  res.json({
    stripe: { enabled: stripeLib.enabled, publishableKey: stripeLib.publishableKey },
    paypal: { enabled: paypalLib.enabled, clientId: paypalLib.clientId },
    invoice: {
      enabled: true,
      bankHolder: process.env.BANK_HOLDER || '',
      bankIban: process.env.BANK_IBAN || '',
      bankBic: process.env.BANK_BIC || '',
    },
  });
});

module.exports = router;
