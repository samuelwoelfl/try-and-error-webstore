function initContact() {
  const form = document.getElementById('contact-form');
  const errorEl = document.getElementById('contact-error');
  form.addEventListener('submit', async (evt) => {
    evt.preventDefault();
    errorEl.hidden = true;
    const fd = new FormData(form);
    const body = {
      name: fd.get('name')?.trim(),
      email: fd.get('email')?.trim(),
      subject: fd.get('subject')?.trim(),
      message: fd.get('message')?.trim(),
    };
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    try {
      await api.contact(body);
      form.hidden = true;
      document.getElementById('contact-success').hidden = false;
    } catch (e) {
      errorEl.textContent = e.message || 'Nachricht konnte nicht gesendet werden.';
      errorEl.hidden = false;
    } finally {
      btn.disabled = false;
    }
  });
}

document.addEventListener('DOMContentLoaded', initContact);
