let currentImageUrl = null;
let currentWorkId = null;

function setDropzoneImage(url) {
  const zone = document.getElementById('image-dropzone');
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

function wireImageUpload() {
  const zone = document.getElementById('image-dropzone');
  const input = document.getElementById('image-input');
  zone.addEventListener('click', () => input.click());
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('is-dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('is-dragover');
    if (e.dataTransfer.files[0]) uploadImage(e.dataTransfer.files[0]);
  });
  input.addEventListener('change', () => {
    if (input.files[0]) uploadImage(input.files[0]);
  });
}

async function uploadImage(file) {
  showAdminError(null);
  try {
    const { url } = await api.adminUpload(file);
    currentImageUrl = url;
    setDropzoneImage(url);
  } catch (e) {
    showAdminError(e.message);
  }
}

function toggleEditionField() {
  const kind = document.querySelector('[name="kind"]').value;
  document.getElementById('edition-field').hidden = kind !== 'edition';
}

function fillForm(work) {
  const form = document.getElementById('editor-form');
  form.title.value = work.title;
  form.kind.value = work.kind;
  form.status.value = work.status;
  form.technique.value = work.technique;
  form.editionLabel.value = work.editionLabel || '';
  form.widthCm.value = work.widthCm || '';
  form.heightCm.value = work.heightCm || '';
  form.year.value = work.year || '';
  form.priceEuro.value = work.priceCents ? (work.priceCents / 100) : '';
  form.description.value = work.description || '';
  form.isHidden.checked = !!work.isHidden;
  currentImageUrl = work.imageUrl || null;
  setDropzoneImage(currentImageUrl);
  toggleEditionField();
}

function readForm() {
  const form = document.getElementById('editor-form');
  const fd = new FormData(form);
  return {
    title: fd.get('title')?.trim(),
    kind: fd.get('kind'),
    status: fd.get('status'),
    technique: fd.get('technique')?.trim(),
    editionLabel: fd.get('editionLabel')?.trim(),
    widthCm: fd.get('widthCm'),
    heightCm: fd.get('heightCm'),
    year: fd.get('year'),
    priceEuro: fd.get('priceEuro'),
    description: fd.get('description')?.trim(),
    imageUrl: currentImageUrl,
    isHidden: form.isHidden.checked,
  };
}

async function initEditor() {
  const session = await requireAdminAuth();
  if (!session) return;

  wireImageUpload();
  document.querySelector('[name="kind"]').addEventListener('change', toggleEditionField);
  toggleEditionField();

  const params = new URLSearchParams(location.search);
  currentWorkId = params.get('id');
  if (currentWorkId) {
    document.getElementById('editor-title').textContent = 'Werk bearbeiten';
    try {
      const work = await api.work(currentWorkId);
      fillForm(work);
    } catch (e) {
      showAdminError('Werk konnte nicht geladen werden.');
    }
  }

  document.getElementById('editor-form').addEventListener('submit', async (evt) => {
    evt.preventDefault();
    showAdminError(null);
    const body = readForm();
    try {
      if (currentWorkId) {
        await api.adminUpdateWork(currentWorkId, body);
      } else {
        await api.adminCreateWork(body);
      }
      location.href = '/admin/werke.html';
    } catch (e) {
      showAdminError(e.message);
    }
  });
}

document.addEventListener('DOMContentLoaded', initEditor);
