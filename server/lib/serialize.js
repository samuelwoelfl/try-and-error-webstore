function serializeWork(row) {
  return {
    id: row.id,
    title: row.title,
    technique: row.technique,
    widthCm: row.width_cm,
    heightCm: row.height_cm,
    year: row.year,
    priceCents: row.price_cents,
    kind: row.kind,
    editionLabel: row.edition_label,
    status: row.status,
    description: row.description,
    imageUrl: row.image_url || null,
    sortOrder: row.sort_order,
  };
}

function serializeSettings(row) {
  return {
    heroTitle: row.hero_title,
    heroSub: row.hero_sub,
    aboutTitle: row.about_title,
    aboutText: row.about_text,
    logoUrl: row.logo_url || null,
  };
}

function serializeOrder(row, items) {
  return {
    id: row.id,
    orderNumber: row.order_number,
    status: row.status,
    paymentMethod: row.payment_method,
    firstName: row.first_name,
    lastName: row.last_name,
    email: row.email,
    street: row.street,
    zip: row.zip,
    city: row.city,
    totalCents: row.total_cents,
    currency: row.currency,
    createdAt: row.created_at,
    paidAt: row.paid_at,
    items: (items || []).map((i) => ({
      workId: i.work_id,
      title: i.title,
      metaLine: i.meta_line,
      unitPriceCents: i.unit_price_cents,
      qty: i.qty,
      kind: i.kind,
    })),
  };
}

module.exports = { serializeWork, serializeSettings, serializeOrder };
