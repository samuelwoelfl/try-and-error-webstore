<?php
declare(strict_types=1);

function serialize_work(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'technique' => $row['technique'],
        'widthCm' => (int) $row['width_cm'],
        'heightCm' => (int) $row['height_cm'],
        'year' => (int) $row['year'],
        'priceCents' => (int) $row['price_cents'],
        'kind' => $row['kind'],
        'editionLabel' => $row['edition_label'],
        'status' => $row['status'],
        'description' => $row['description'],
        'imageUrl' => $row['image_url'] ?: null,
        'sortOrder' => (int) $row['sort_order'],
        'isHidden' => (bool) ($row['is_hidden'] ?? false),
    ];
}

/** Public-facing subset — never include order_notification_email/order_sender_email here, they're operational contact details, not storefront content. */
function serialize_settings_public(array $row): array
{
    return [
        'heroTitle' => $row['hero_title'],
        'heroSub' => $row['hero_sub'],
        'heroEyebrow' => $row['hero_eyebrow'],
        'heroImageAUrl' => $row['hero_image_a_url'] ?: null,
        'heroImageBUrl' => $row['hero_image_b_url'] ?: null,
        'worksEyebrow' => $row['works_eyebrow'],
        'worksTitle' => $row['works_title'],
        'worksCountLabel' => $row['works_count_label'],
        'aboutTitle' => $row['about_title'],
        'aboutText' => $row['about_text'],
        'aboutEyebrow' => $row['about_eyebrow'],
        'aboutImageUrl' => $row['about_image_url'] ?: null,
        'logoUrl' => $row['logo_url'] ?: null,
    ];
}

/** Admin view — everything from the public subset plus operational settings. */
function serialize_settings(array $row): array
{
    return serialize_settings_public($row) + [
        'orderNotificationEmail' => $row['order_notification_email'] ?: null,
        'orderSenderName' => $row['order_sender_name'] ?: null,
        'orderSenderEmail' => $row['order_sender_email'] ?: null,
    ];
}

function serialize_order(array $row, array $items): array
{
    return [
        'id' => (int) $row['id'],
        'orderNumber' => $row['order_number'],
        'status' => $row['status'],
        'paymentMethod' => $row['payment_method'],
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'email' => $row['email'],
        'street' => $row['street'],
        'zip' => $row['zip'],
        'city' => $row['city'],
        'totalCents' => (int) $row['total_cents'],
        'currency' => $row['currency'],
        'createdAt' => $row['created_at'],
        'paidAt' => $row['paid_at'],
        'fulfilledAt' => $row['fulfilled_at'] ?? null,
        'items' => array_map(static fn (array $i) => [
            'workId' => (int) $i['work_id'],
            'title' => $i['title'],
            'metaLine' => $i['meta_line'],
            'unitPriceCents' => (int) $i['unit_price_cents'],
            'qty' => (int) $i['qty'],
            'kind' => $i['kind'],
        ], $items),
    ];
}

/** Inverse of serialize_work() — shapes an admin API request body into a DB row. */
function work_record_from_request(array $body): array
{
    $kind = ($body['kind'] ?? '') === 'edition' ? 'edition' : 'unique';
    $status = ($body['status'] ?? '') === 'verkauft' ? 'verkauft' : 'verfuegbar';
    return [
        'title' => trim((string) ($body['title'] ?? '')) ?: 'Ohne Titel',
        'technique' => trim((string) ($body['technique'] ?? '')),
        'width_cm' => (int) ($body['widthCm'] ?? 0),
        'height_cm' => (int) ($body['heightCm'] ?? 0),
        'year' => (int) ($body['year'] ?? date('Y')),
        'price_cents' => (int) round((float) ($body['priceEuro'] ?? 0) * 100),
        'kind' => $kind,
        'edition_label' => $kind === 'edition' ? (trim((string) ($body['editionLabel'] ?? '')) ?: 'Edition') : '',
        'status' => $status,
        'description' => trim((string) ($body['description'] ?? '')),
        'image_url' => $body['imageUrl'] ?? null,
        'is_hidden' => !empty($body['isHidden']) ? 1 : 0,
    ];
}

function meta_line_for(array $work): string
{
    return $work['kind'] === 'unique'
        ? sprintf('%s · %d × %d cm', $work['technique'], $work['width_cm'], $work['height_cm'])
        : sprintf('%s · %s', $work['edition_label'], $work['technique']);
}
