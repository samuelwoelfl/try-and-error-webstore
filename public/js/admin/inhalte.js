const imageState = { heroImageAUrl: null, heroImageBUrl: null, aboutImageUrl: null };

function setImagePreview(zoneId, url) {
  const zone = document.getElementById(zoneId);
  zone.querySelectorAll('img').forEach((img) => img.remove());
  if (url) {
    const img = document.createElement('img');
    img.src = url;
    zone.appendChild(img);
    zone.querySelector('.image-dropzone__icon').style.display = 'none';
    zone.querySelector('.image-dropzone__label').style.display = 'none';
  } else {
    zone.querySelector('.image-dropzone__icon').style.display = '';
    zone.querySelector('.image-dropzone__label').style.display = '';
  }
}

function wireImageUpload(zoneId, inputId, stateKey) {
  const zone = document.getElementById(zoneId);
  const input = document.getElementById(inputId);
  const upload = async (file) => {
    showAdminError(null);
    try {
      const { url } = await api.adminUpload(file);
      imageState[stateKey] = url;
      setImagePreview(zoneId, url);
    } catch (e) {
      showAdminError(e.message);
    }
  };
  zone.addEventListener('click', () => input.click());
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('is-dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('is-dragover');
    if (e.dataTransfer.files[0]) upload(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', () => {
    if (input.files[0]) upload(input.files[0]);
  });
}

async function initInhalte() {
  const session = await requireAdminAuth();
  if (!session) return;

  wireImageUpload('hero-a-dropzone', 'hero-a-input', 'heroImageAUrl');
  wireImageUpload('hero-b-dropzone', 'hero-b-input', 'heroImageBUrl');
  wireImageUpload('about-dropzone', 'about-input', 'aboutImageUrl');

  const form = document.getElementById('content-form');
  try {
    const settings = await api.adminSettings();
    form.heroEyebrow.value = settings.heroEyebrow || '';
    form.heroTitle.value = settings.heroTitle || '';
    form.heroSub.value = settings.heroSub || '';
    form.worksEyebrow.value = settings.worksEyebrow || '';
    form.worksTitle.value = settings.worksTitle || '';
    form.worksCountLabel.value = settings.worksCountLabel || '';
    form.aboutEyebrow.value = settings.aboutEyebrow || '';
    form.aboutTitle.value = settings.aboutTitle || '';
    form.aboutText.value = settings.aboutText || '';
    form.orderNotificationEmail.value = settings.orderNotificationEmail || '';
    form.orderSenderName.value = settings.orderSenderName || '';
    form.orderSenderEmail.value = settings.orderSenderEmail || '';

    imageState.heroImageAUrl = settings.heroImageAUrl || null;
    imageState.heroImageBUrl = settings.heroImageBUrl || null;
    imageState.aboutImageUrl = settings.aboutImageUrl || null;
    setImagePreview('hero-a-dropzone', imageState.heroImageAUrl);
    setImagePreview('hero-b-dropzone', imageState.heroImageBUrl);
    setImagePreview('about-dropzone', imageState.aboutImageUrl);
  } catch (e) {
    showAdminError('Inhalte konnten nicht geladen werden.');
  }

  form.addEventListener('submit', async (evt) => {
    evt.preventDefault();
    showAdminError(null);
    try {
      await api.adminUpdateSettings({
        heroEyebrow: form.heroEyebrow.value,
        heroTitle: form.heroTitle.value,
        heroSub: form.heroSub.value,
        heroImageAUrl: imageState.heroImageAUrl,
        heroImageBUrl: imageState.heroImageBUrl,
        worksEyebrow: form.worksEyebrow.value,
        worksTitle: form.worksTitle.value,
        worksCountLabel: form.worksCountLabel.value,
        aboutEyebrow: form.aboutEyebrow.value,
        aboutTitle: form.aboutTitle.value,
        aboutText: form.aboutText.value,
        aboutImageUrl: imageState.aboutImageUrl,
        orderNotificationEmail: form.orderNotificationEmail.value,
        orderSenderName: form.orderSenderName.value,
        orderSenderEmail: form.orderSenderEmail.value,
      });
      flashSaved();
    } catch (e) {
      showAdminError(e.message);
    }
  });
}

document.addEventListener('DOMContentLoaded', initInhalte);
