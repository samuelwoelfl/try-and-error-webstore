<?php
declare(strict_types=1);

/**
 * Order confirmation email — sent for every completed checkout (Stripe/PayPal once
 * paid, Rechnung immediately). Table-based, fully inline-styled HTML for consistent
 * rendering across Outlook/Gmail/Apple Mail/etc., plus a plain-text alternative.
 *
 * @param array $order  ['orderNumber','firstName','lastName','email','street','zip','city',
 *                        'totalCents','paymentMethod','status']
 * @param array $items  list of ['title','metaLine','unitPriceCents','qty']
 * @param array|null $bank ['bankHolder','bankIban','bankBic'] — pass only for pending
 *                          Rechnung/Überweisung orders that still need to be paid
 * @return array{subject: string, text: string, html: string}
 */
function build_order_confirmation_email(array $order, array $items, ?array $bank = null): array
{
    $orderUrl = site_base_url() . '/bestellung.html?order=' . urlencode($order['orderNumber']);

    $payLabels = [
        'stripe' => 'Kredit-/Debitkarte',
        'paypal' => 'PayPal',
        'invoice' => 'Rechnung / Überweisung',
    ];
    $payLabel = $payLabels[$order['paymentMethod']] ?? $order['paymentMethod'];
    $subject = "Bestellbestätigung {$order['orderNumber']} — Try & Error";

    $text = email_order_text($order, $items, $payLabel, $bank, $orderUrl);
    $html = email_order_html($order, $items, $payLabel, $bank, $orderUrl);

    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}

function email_order_text(array $order, array $items, string $payLabel, ?array $bank, string $orderUrl): string
{
    $lines = array_map(
        static fn (array $i) => "{$i['qty']}× {$i['title']} ({$i['metaLine']}) — " . fmt_euro($i['unitPriceCents'] * $i['qty']),
        $items
    );

    $out = "Hallo {$order['firstName']},\n\n";
    $out .= "vielen Dank für deine Bestellung bei Try & Error!\n\n";
    $out .= "Bestellnummer: {$order['orderNumber']}\n\n";
    $out .= implode("\n", $lines) . "\n\n";
    $out .= "Gesamt: " . fmt_euro($order['totalCents']) . " (Versand inklusive)\n";
    $out .= "Zahlungsart: $payLabel\n\n";

    if ($bank !== null) {
        $out .= "Bitte überweise den Betrag unter Angabe der Bestellnummer an:\n";
        $out .= "{$bank['bankHolder']}\nIBAN: {$bank['bankIban']}\nBIC: {$bank['bankBic']}\n";
        $out .= "Der Versand erfolgt nach Zahlungseingang.\n\n";
    }

    $out .= "Lieferadresse:\n{$order['firstName']} {$order['lastName']}\n{$order['street']}\n{$order['zip']} {$order['city']}\n\n";
    $out .= "Bestellung ansehen: $orderUrl\n\n";
    $out .= "Wir melden uns mit dem Versand.\nTry & Error · Atelier Stuttgart";

    return $out;
}

function email_order_html(array $order, array $items, string $payLabel, ?array $bank, string $orderUrl): string
{
    $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $fontHead = "Georgia, 'Times New Roman', Times, serif";
    $fontBody = "Arial, Helvetica, sans-serif";
    $textColor = '#221f1c';
    $textSecondary = '#4a453f';
    $textTertiary = '#8a8075';
    $border = '#e5e1da';
    $ivory = '#F6F3EE';

    $itemRows = '';
    foreach ($items as $i) {
        $itemRows .= '
        <tr>
          <td style="padding:14px 0;border-bottom:1px solid ' . $border . ';font-family:' . $fontBody . ';font-size:14px;color:' . $textColor . ';">
            <div style="font-family:' . $fontHead . ';font-size:16px;color:' . $textColor . ';">' . $e($i['title']) . '</div>
            <div style="font-size:12.5px;color:' . $textTertiary . ';margin-top:3px;">' . $e($i['metaLine']) . ($i['qty'] > 1 ? ' · ' . (int) $i['qty'] . '×' : '') . '</div>
          </td>
          <td align="right" style="padding:14px 0;border-bottom:1px solid ' . $border . ';font-family:' . $fontBody . ';font-size:14px;color:' . $textColor . ';white-space:nowrap;vertical-align:top;">' . $e(fmt_euro($i['unitPriceCents'] * $i['qty'])) . '</td>
        </tr>';
    }

    $bankBlock = '';
    if ($bank !== null) {
        $bankBlock = '
        <tr><td style="padding:0 40px 32px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $ivory . ';border:1px solid ' . $border . ';">
            <tr><td style="padding:22px 24px;font-family:' . $fontBody . ';font-size:13.5px;line-height:1.6;color:' . $textSecondary . ';">
              <div style="font-family:' . $fontHead . ';font-size:15px;color:' . $textColor . ';margin-bottom:10px;">Bitte überweise den Betrag unter Angabe der Bestellnummer</div>
              <strong style="color:' . $textColor . ';">' . $e($bank['bankHolder']) . '</strong><br>
              IBAN: ' . $e($bank['bankIban']) . '<br>
              BIC: ' . $e($bank['bankBic']) . '<br>
              Verwendungszweck: ' . $e($order['orderNumber']) . '<br><br>
              Der Versand erfolgt nach Zahlungseingang.
            </td></tr>
          </table>
        </td></tr>';
    }

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . $e("Bestellbestätigung {$order['orderNumber']}") . '</title>
</head>
<body style="margin:0;padding:0;background-color:' . $ivory . ';">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $ivory . ';">
<tr><td align="center" style="padding:32px 16px;">

<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background-color:#ffffff;">

  <tr><td style="padding:32px 40px 24px;border-bottom:1px solid ' . $border . ';">
    <div style="font-family:' . $fontHead . ';font-size:20px;font-weight:bold;color:' . $textColor . ';letter-spacing:0.02em;">Try &amp; Error</div>
    <div style="font-family:' . $fontBody . ';font-size:10px;letter-spacing:0.3em;text-transform:uppercase;color:' . $textTertiary . ';padding-top:4px;">Atelier Stuttgart</div>
  </td></tr>

  <tr><td style="padding:40px 40px 8px;text-align:center;">
    <div style="font-family:' . $fontBody . ';font-size:26px;color:' . $textColor . ';">&#10003;</div>
    <div style="font-family:' . $fontHead . ';font-size:26px;color:' . $textColor . ';padding-top:10px;">Vielen Dank!</div>
    <div style="font-family:' . $fontBody . ';font-size:14.5px;line-height:1.6;color:' . $textSecondary . ';padding-top:10px;">Hallo ' . $e($order['firstName']) . ', deine Bestellung ist bei uns eingegangen.</div>
  </td></tr>

  <tr><td style="padding:24px 40px 0;text-align:center;">
    <div style="font-family:' . $fontBody . ';font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:' . $textTertiary . ';">Bestellnummer</div>
    <div style="font-family:' . $fontBody . ';font-size:18px;letter-spacing:0.08em;color:' . $textColor . ';padding-top:4px;">' . $e($order['orderNumber']) . '</div>
  </td></tr>

  <tr><td style="padding:32px 40px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid ' . $border . ';">
      ' . $itemRows . '
      <tr>
        <td style="padding:12px 0 0;font-family:' . $fontBody . ';font-size:13.5px;color:' . $textTertiary . ';">Versand</td>
        <td align="right" style="padding:12px 0 0;font-family:' . $fontBody . ';font-size:13.5px;color:' . $textTertiary . ';">inklusive</td>
      </tr>
      <tr>
        <td style="padding:14px 0 24px;border-top:1px solid ' . $border . ';margin-top:10px;font-family:' . $fontHead . ';font-size:19px;color:' . $textColor . ';padding-top:14px;">Gesamt</td>
        <td align="right" style="padding:14px 0 24px;border-top:1px solid ' . $border . ';font-family:' . $fontHead . ';font-size:19px;color:' . $textColor . ';padding-top:14px;">' . $e(fmt_euro($order['totalCents'])) . '</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:0 40px 32px;font-family:' . $fontBody . ';font-size:13.5px;color:' . $textSecondary . ';">
    Zahlungsart: <strong style="color:' . $textColor . ';">' . $e($payLabel) . '</strong>
  </td></tr>

  ' . $bankBlock . '

  <tr><td style="padding:0 40px 32px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid ' . $border . ';padding-top:24px;">
      <tr><td style="padding-top:24px;font-family:' . $fontBody . ';font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:' . $textTertiary . ';">Lieferadresse</td></tr>
      <tr><td style="padding-top:8px;font-family:' . $fontBody . ';font-size:14px;line-height:1.6;color:' . $textColor . ';">
        ' . $e($order['firstName'] . ' ' . $order['lastName']) . '<br>
        ' . $e($order['street']) . '<br>
        ' . $e($order['zip'] . ' ' . $order['city']) . '
      </td></tr>
    </table>
  </td></tr>

  <tr><td style="padding:0 40px 40px;" align="center">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
      <tr><td style="background-color:#000000;">
        <a href="' . $e($orderUrl) . '" style="display:inline-block;padding:14px 32px;font-family:' . $fontBody . ';font-size:12.5px;letter-spacing:0.1em;text-transform:uppercase;color:#ffffff;text-decoration:none;">Bestellung ansehen</a>
      </td></tr>
    </table>
  </td></tr>

  <tr><td style="padding:24px 40px 32px;border-top:1px solid ' . $border . ';font-family:' . $fontBody . ';font-size:12px;color:' . $textTertiary . ';text-align:center;">
    Try &amp; Error · Atelier Stuttgart<br>
    Fragen zu deiner Bestellung? Antworte einfach auf diese E-Mail.
  </td></tr>

</table>

</td></tr>
</table>
</body>
</html>';

    return $html;
}
