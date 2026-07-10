let currentLogoUrl = null;

function setLogoPreview(url) {
  const zone = document.getElementById('logo-dropzone');
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

function wireLogoUpload() {
  const zone = document.getElementById('logo-dropzone');
  const input = document.getElementById('logo-input');
  zone.addEventListener('click', () => input.click());
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('is-dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('is-dragover');
    if (e.dataTransfer.files[0]) uploadLogo(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', () => {
    if (input.files[0]) uploadLogo(input.files[0]);
  });
}

async function uploadLogo(file) {
  showAdminError(null);
  try {
    const { url } = await api.adminUpload(file);
    currentLogoUrl = url;
    setLogoPreview(url);
  } catch (e) {
    showAdminError(e.message);
  }
}

async function initInhalte() {
  const session = await requireAdminAuth();
  if (!session) return;

  wireLogoUpload();

  const form = document.getElementById('content-form');
  try {
    const settings = await api.adminSettings();
    form.heroTitle.value = settings.heroTitle || '';
    form.heroSub.value = settings.heroSub || '';
    form.aboutTitle.value = settings.aboutTitle || '';
    form.aboutText.value = settings.aboutText || '';
    currentLogoUrl = settings.logoUrl || null;
    setLogoPreview(currentLogoUrl);
  } catch (e) {
    showAdminError('Inhalte konnten nicht geladen werden.');
  }

  form.addEventListener('submit', async (evt) => {
    evt.preventDefault();
    showAdminError(null);
    try {
      await api.adminUpdateSettings({
        heroTitle: form.heroTitle.value,
        heroSub: form.heroSub.value,
        aboutTitle: form.aboutTitle.value,
        aboutText: form.aboutText.value,
        logoUrl: currentLogoUrl,
      });
      flashSaved();
    } catch (e) {
      showAdminError(e.message);
    }
  });
}

document.addEventListener('DOMContentLoaded', initInhalte);
