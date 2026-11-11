// Admin Rotana Bot page (admin/bot.php): reads the PDF in the browser (text
// and photos), uploads the photos, submits the text; and runs the
// "Test the bot" box. PDF.js (assets/vendor/pdfjs) only loads when a PDF is
// actually imported.
import { pdfToSheet } from './pdf-text.js';

const PHOTO_BATCH = 8; // photos per upload request (PHP accepts 20 files per request)

const importForm = document.getElementById('botImportForm');
if (importForm) {
  const fileInput = importForm.querySelector('#botSheetFile');
  const textField = importForm.querySelector('input[name="sheet_text"]');
  const nameField = importForm.querySelector('input[name="sheet_name"]');
  const imagesField = importForm.querySelector('input[name="sheet_images"]');
  const paste = importForm.querySelector('textarea[name="pasted_text"]');
  const status = importForm.querySelector('.bot-import-status');
  const button = importForm.querySelector('button[type="submit"]');
  const existing = Number(importForm.dataset.existing || 0);
  const uploadUrl = importForm.dataset.upload;

  const show = (message, isError) => {
    status.hidden = false;
    status.textContent = message;
    status.classList.toggle('error', Boolean(isError));
  };

  const readFile = async (file) => {
    if (/\.pdf$/i.test(file.name) || file.type === 'application/pdf') {
      const pdfjsLib = await import('../vendor/pdfjs/pdf.min.js');
      pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.js', import.meta.url).href;
      return pdfToSheet(pdfjsLib, new Uint8Array(await file.arrayBuffer()), {
        onProgress: ({ page, pages }) => show(`Reading ${file.name}: page ${page} of ${pages}…`),
      });
    }
    return { text: await file.text(), images: [] };
  };

  // Uploads photos in small batches; returns { ref: saved file name }.
  const uploadPhotos = async (images) => {
    const saved = {};
    for (let i = 0; i < images.length; i += PHOTO_BATCH) {
      show(`Saving photos: ${Math.min(i + PHOTO_BATCH, images.length)} of ${images.length}…`);
      const body = new FormData();
      body.append('action', 'upload_images');
      for (const img of images.slice(i, i + PHOTO_BATCH)) {
        body.append('refs[]', String(img.ref));
        body.append('images[]', img.blob, `photo-${img.ref}.jpg`);
      }
      const response = await fetch(uploadUrl, { method: 'POST', body, headers: { Accept: 'application/json' } });
      let result = null;
      try { result = await response.json(); } catch (e) { /* not JSON: logged out, or a server error page */ }
      if (!response.ok || !result) {
        throw new Error('the photos could not be saved. Are you still logged in? Reload the page and try again');
      }
      Object.assign(saved, result.saved || {});
    }
    return saved;
  };

  importForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const file = fileInput.files[0];
    if (!file && !paste.value.trim()) {
      show('Choose a PDF, or paste the text, first.', true);
      return;
    }
    const mode = importForm.querySelector('input[name="mode"]:checked').value;
    if (mode === 'replace' && existing > 0 &&
        !confirm(`This replaces all ${existing} current answers, including any you edited. Continue?`)) {
      return;
    }

    button.disabled = true;
    try {
      textField.value = '';
      nameField.value = '';
      imagesField.value = '';
      if (file) {
        show(`Reading ${file.name}…`);
        const { text, images } = await readFile(file);
        if (!text.replace(/\{\{[^}]*\}\}/g, '').trim() && !images.length) {
          show('This PDF has no text that can be read. It may be a scanned image: export the original from Word or Google Docs as a PDF, or paste the text below.', true);
          button.disabled = false;
          return;
        }
        textField.value = text;
        nameField.value = file.name;
        if (images.length) imagesField.value = JSON.stringify(await uploadPhotos(images));
      }
      show('Importing…');
      importForm.submit();
    } catch (err) {
      console.error(err);
      show(err && err.name === 'PasswordException'
        ? 'This PDF is password-protected. Save a copy without the password and try again.'
        : `Couldn't import this file: ${err && err.message ? err.message : err}.`, true);
      button.disabled = false;
    }
  });
}

const testForm = document.getElementById('botTestForm');
if (testForm) {
  const out = document.getElementById('botTestResult');
  testForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const q = testForm.elements.q.value.trim();
    if (!q) return;
    out.hidden = false;
    out.textContent = 'Asking…';
    try {
      const response = await fetch(testForm.dataset.api, { method: 'POST', body: new URLSearchParams({ q }) });
      const res = await response.json();
      const meta = document.createElement('span');
      meta.className = 'bot-test-meta';
      if (res.type === 'answer') {
        meta.textContent = `Matched: "${res.matched}"${res.confident ? '' : ' (a weaker match)'}`;
      } else if (res.type === 'fallback') {
        meta.textContent = 'No match good enough. Visitors will see:';
      } else {
        meta.textContent = 'Small-talk reply:';
      }
      let text = res.text;
      if (res.suggestions && res.suggestions.length) {
        text += '\n\n' + res.suggestions_label + '\n' + res.suggestions.map((s) => '• ' + s).join('\n');
      }
      const nodes = [meta, document.createTextNode(text)];
      if (res.images && res.images.length) {
        const strip = document.createElement('div');
        strip.className = 'bot-test-images';
        for (const src of res.images) {
          const img = document.createElement('img');
          img.src = src;
          img.alt = '';
          img.loading = 'lazy';
          strip.appendChild(img);
        }
        nodes.push(strip);
      }
      out.replaceChildren(...nodes);
    } catch (err) {
      out.textContent = "Couldn't reach the bot. Is the server running?";
    }
  });
}
