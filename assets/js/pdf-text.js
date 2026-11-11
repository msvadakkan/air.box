// Reads an answer sheet or document out of a PDF with PDF.js: its text,
// laid out as lines and paragraphs, and the photos/diagrams on its pages.
// Used by assets/js/bot-admin.js; includes/bot.php parses the text.
//
// Besides ordinary lines and blank lines (paragraph breaks), the text has
// marker lines the server understands:
//   {{page:N}}  page N starts here
//   {{h}}Title  a heading (text noticeably larger than the body text)
//   {{img:N}}   photo number N (images[N]) sits here in the reading order

const RTL = /[֐-ࣿיִ-﷿ﹰ-﻿]/;
const LTR = /[A-Za-z0-9À-ɏ]/;
// Arabic letters as drawn (presentation forms) and Latin ligatures such as
// "ﬁ". Many PDFs store text this way; NFKC turns them back into plain letters.
const SHAPED = /[ﬀ-ﬆיִ-﷿ﹰ-﻿]+/g;

// Letters only Persian/Urdu use. If a line has none, it's Arabic, and the
// Persian-style yeh/heh/keheh that some fonts map their shared glyphs to are
// turned back into the Arabic letters they look identical to.
const PERSIAN_ONLY = /[پچژگٹڈڑںے]/;
const ARABIC_LETTER = '[\\u0620-\\u064A\\u0671-\\u06D3]';

const MIN_PHOTO_SIDE = 40; // points; smaller images are icons and bullets
const MAX_PHOTO_PX = 1280; // longest side of a saved photo
const MAX_RENDER_SCALE = 2.5;

function arabicLetters(text) {
  if (!/[یھک]/.test(text) || PERSIAN_ONLY.test(text)) return text;
  return text
    .replace(new RegExp('\\u06CC(?=' + ARABIC_LETTER + ')', 'g'), 'ي') // yeh inside a word
    .replace(/ی/g, 'ى') // dotless yeh at the end of a word
    .replace(/ھ/g, 'ه')
    .replace(/ک/g, 'ك');
}

const directionOf = (str) => (RTL.test(str) ? 'R' : LTR.test(str) ? 'L' : 'N');

// In a right-to-left line, PDF.js leaves English/number pieces in visual
// order, so ".A319" really means "A319." Split leading/trailing punctuation
// off those pieces so reorder() can put it on the right side.
function splitNeutralEdges(p) {
  const m = /^([^A-Za-z0-9À-ɏ]*)(.*?)([^A-Za-z0-9À-ɏ]*)$/s.exec(p.str);
  if (!m || (!m[1] && !m[3]) || !m[2]) return [p];
  const per = p.w / Math.max(1, p.str.length);
  const out = [];
  if (m[1]) out.push({ ...p, str: m[1], w: per * m[1].length });
  out.push({ ...p, str: m[2], x: p.x + per * m[1].length, w: per * m[2].length });
  if (m[3]) out.push({ ...p, str: m[3], x: p.x + per * (m[1].length + m[2].length), w: per * m[3].length });
  return out;
}

// Pieces in visual (left-to-right) order -> text in reading order, for a
// line whose main direction is `base` ('L' or 'R'). A simplified Unicode
// bidi: neutral pieces (spaces, punctuation) take the direction of the
// text around them, right-to-left runs are read right to left, and numbers
// or English words inside Arabic keep their own order. Each piece's own
// string is already in reading order (PDF.js handles that).
function reorder(pieces, base) {
  const dirs = pieces.map((p) => directionOf(p.str));
  const resolved = dirs.map((d, i) => {
    if (d !== 'N') return d;
    let a = i - 1;
    while (a >= 0 && dirs[a] === 'N') a--;
    let b = i + 1;
    while (b < dirs.length && dirs[b] === 'N') b++;
    const before = a >= 0 ? dirs[a] : base;
    const after = b < dirs.length ? dirs[b] : base;
    return before === after ? before : base;
  });
  const runs = [];
  pieces.forEach((p, i) => {
    const last = runs[runs.length - 1];
    if (last && last.dir === resolved[i]) last.pieces.push(p);
    else runs.push({ dir: resolved[i], pieces: [p] });
  });
  if (base === 'R') runs.reverse();
  return runs
    .map((r) => (r.dir === 'R' ? r.pieces.slice().reverse() : r.pieces).map((p) => p.str).join(''))
    .join('');
}

function lineText(pieces) {
  const sorted = pieces.slice().sort((a, b) => a.x - b.x);
  const withSpaces = [];
  for (const p of sorted) {
    const last = withSpaces[withSpaces.length - 1];
    if (last) {
      const gap = p.x - (last.x + last.w);
      if (gap > Math.max(p.size, last.size) * 0.15 && !/\s$/.test(last.str) && !/^\s/.test(p.str)) {
        withSpaces.push({ str: ' ', x: last.x + last.w, w: 0, size: p.size });
      }
    }
    withSpaces.push(p);
  }
  const text = withSpaces.map((p) => p.str).join('');
  const rtlChars = (text.match(new RegExp(RTL.source, 'g')) || []).length;
  const ltrChars = (text.match(new RegExp(LTR.source, 'g')) || []).length;
  const base = rtlChars > ltrChars ? 'R' : 'L';
  const visual = base === 'R'
    ? withSpaces.flatMap((p) => (directionOf(p.str) === 'L' ? splitNeutralEdges(p) : [p]))
    : withSpaces;
  const ordered = reorder(visual, base).replace(SHAPED, (s) => s.normalize('NFKC'));
  return arabicLetters(ordered).replace(/\s+/g, ' ').trim();
}

// PDF.js text items -> rows of text at the same height: [{ y, size, text }].
function itemsToRows(items) {
  const rows = [];
  let row = null;
  for (const it of items) {
    if (typeof it.str !== 'string') continue; // marked-content markers
    if (it.str !== '') {
      const size = Math.hypot(it.transform[2], it.transform[3]) || it.height || 10;
      const piece = { str: it.str, x: it.transform[4], w: it.width || 0, size };
      const y = it.transform[5];
      if (row && Math.abs(y - row.y) <= Math.max(size, row.size) * 0.5) {
        row.pieces.push(piece);
        row.size = Math.max(row.size, size);
      } else {
        row = { y, size, pieces: [piece] };
        rows.push(row);
      }
    }
    if (it.hasEOL) row = null;
  }
  return rows
    .map((r) => ({ y: r.y, size: r.size, text: lineText(r.pieces) }))
    .filter((r) => r.text);
}

// Rows -> lines with blank lines between paragraphs: a vertical gap well over
// one line's height, a jump back up (new column) or a change of text size.
// `before(row)` returns marker lines to put in front of a row.
function rowsToLines(rows, { heading = () => false, before = () => [] } = {}) {
  const lines = [];
  let prev = null;
  for (const r of rows) {
    lines.push(...before(r));
    if (prev) {
      const drop = prev.y - r.y;
      const big = Math.max(prev.size, r.size);
      const small = Math.min(prev.size, r.size);
      if (drop > big * 1.65 || drop < -big || big / small > 1.25) lines.push('');
    }
    lines.push(heading(r) ? '{{h}}' + r.text : r.text);
    prev = r;
  }
  return lines;
}

// Text of one page's items as lines (no markers). Kept for tests and tools.
export function itemsToLines(items) {
  return rowsToLines(itemsToRows(items));
}

// ------------------------------------------------------------------ photos --

const IDENTITY = [1, 0, 0, 1, 0, 0];
const multiply = (m, n) => [
  m[0] * n[0] + m[2] * n[1], m[1] * n[0] + m[3] * n[1],
  m[0] * n[2] + m[2] * n[3], m[1] * n[2] + m[3] * n[3],
  m[0] * n[4] + m[2] * n[5] + m[4], m[1] * n[4] + m[3] * n[5] + m[5],
];

// Where each image is drawn on the page, in PDF points: follows the drawing
// instructions, tracking the current position/scale, and records the box of
// every picture painted (an image fills the unit square of the current matrix).
function findPhotos(OPS, opList, view) {
  const found = [];
  let ctm = IDENTITY;
  const stack = [];
  for (let i = 0; i < opList.fnArray.length; i++) {
    const fn = opList.fnArray[i];
    const args = opList.argsArray[i];
    if (fn === OPS.save) stack.push(ctm);
    else if (fn === OPS.restore) ctm = stack.pop() || IDENTITY;
    else if (fn === OPS.transform) ctm = multiply(ctm, args);
    else if (fn === OPS.paintFormXObjectBegin) {
      stack.push(ctm);
      if (Array.isArray(args[0]) && args[0].length === 6) ctm = multiply(ctm, args[0]);
    } else if (fn === OPS.paintFormXObjectEnd) ctm = stack.pop() || IDENTITY;
    else if (fn === OPS.paintImageXObject || fn === OPS.paintInlineImageXObject) {
      const pxWidth = fn === OPS.paintImageXObject ? args[1] : (args[0] && args[0].width) || 0;
      const xs = [ctm[4], ctm[0] + ctm[4], ctm[2] + ctm[4], ctm[0] + ctm[2] + ctm[4]];
      const ys = [ctm[5], ctm[1] + ctm[5], ctm[3] + ctm[5], ctm[1] + ctm[3] + ctm[5]];
      // Clip to the page: parts drawn off the page aren't visible.
      const x1 = Math.max(Math.min(...xs), view[0]);
      const y1 = Math.max(Math.min(...ys), view[1]);
      const x2 = Math.min(Math.max(...xs), view[2]);
      const y2 = Math.min(Math.max(...ys), view[3]);
      if (x2 - x1 < MIN_PHOTO_SIDE || y2 - y1 < MIN_PHOTO_SIDE) continue;
      const sig = [x1, y1, x2 - x1, y2 - y1].map((v) => Math.round(v / 4)).join(':');
      if (found.some((f) => f.sig === sig)) continue; // painted twice in the same place
      found.push({ x1, y1, x2, y2, sig, pxPerPt: pxWidth / (Math.max(...xs) - Math.min(...xs) || 1) });
    }
  }
  return found;
}

// Renders the page and cuts each photo out of it, as JPEG blobs. Rendering
// (rather than copying the raw image data) gives exactly what the page shows,
// whatever the image's format, colour space or transparency.
async function cropPhotos(page, found) {
  const scale = Math.min(MAX_RENDER_SCALE, Math.max(1, ...found.map((f) => f.pxPerPt)));
  const viewport = page.getViewport({ scale });
  const canvas = document.createElement('canvas');
  canvas.width = Math.ceil(viewport.width);
  canvas.height = Math.ceil(viewport.height);
  await page.render({ canvas, viewport }).promise;

  const photos = [];
  for (const f of found) {
    const [a, b] = viewport.convertToViewportPoint(f.x1, f.y1);
    const [c, d] = viewport.convertToViewportPoint(f.x2, f.y2);
    const sx = Math.max(0, Math.floor(Math.min(a, c)));
    const sy = Math.max(0, Math.floor(Math.min(b, d)));
    const sw = Math.min(canvas.width, Math.ceil(Math.max(a, c))) - sx;
    const sh = Math.min(canvas.height, Math.ceil(Math.max(b, d))) - sy;
    if (sw < 16 || sh < 16) continue;
    const k = Math.min(1, MAX_PHOTO_PX / Math.max(sw, sh));
    const out = document.createElement('canvas');
    out.width = Math.round(sw * k);
    out.height = Math.round(sh * k);
    out.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, out.width, out.height);
    const blob = await new Promise((resolve) => out.toBlob(resolve, 'image/jpeg', 0.85));
    if (blob) photos.push({ blob, cy: (f.y1 + f.y2) / 2, sig: f.sig, width: out.width, height: out.height });
  }
  canvas.width = canvas.height = 0; // free the page bitmap now, not at garbage collection
  return photos;
}

// ---------------------------------------------------------------- document --

/**
 * Reads a whole PDF. Returns { text, images: [{ ref, blob, width, height }] }.
 * withImages: false skips photos (no canvas needed, e.g. in Node).
 * onProgress({ page, pages }) is called as each page is read.
 */
export async function pdfToSheet(pdfjsLib, data, { withImages = true, onProgress = () => {} } = {}) {
  const task = pdfjsLib.getDocument({
    data,
    isEvalSupported: false,
    verbosity: pdfjsLib.VerbosityLevel ? pdfjsLib.VerbosityLevel.ERRORS : 0,
  });
  const pages = [];
  try {
    const pdf = await task.promise;
    for (let p = 1; p <= pdf.numPages; p++) {
      onProgress({ page: p, pages: pdf.numPages });
      const page = await pdf.getPage(p);
      const rows = itemsToRows((await page.getTextContent()).items);
      let photos = [];
      if (withImages) {
        const found = findPhotos(pdfjsLib.OPS, await page.getOperatorList(), page.view);
        if (found.length) photos = await cropPhotos(page, found);
      }
      pages.push({ rows, photos });
      page.cleanup();
    }
  } finally {
    await task.destroy();
  }
  return assemble(pages);
}

function assemble(pages) {
  // Body text size: the size most of the text is set in.
  const chars = new Map();
  for (const { rows } of pages) {
    for (const r of rows) {
      const s = Math.round(r.size * 2) / 2;
      chars.set(s, (chars.get(s) || 0) + r.text.length);
    }
  }
  let body = 0;
  let most = -1;
  for (const [s, n] of chars) if (n > most) { most = n; body = s; }

  // A picture in the same place on many pages is a logo or page decoration.
  const onPages = new Map();
  pages.forEach(({ photos }) => new Set(photos.map((ph) => ph.sig)).forEach((sig) => onPages.set(sig, (onPages.get(sig) || 0) + 1)));
  const decoration = (sig) => onPages.get(sig) >= 3 && onPages.get(sig) >= pages.length * 0.25;

  const images = [];
  const lines = [];
  pages.forEach(({ rows, photos }, i) => {
    lines.push(`{{page:${i + 1}}}`);

    // The page's heading: its largest text, if clearly bigger than the body
    // text and short (a title, not a paragraph).
    const max = Math.max(0, ...rows.map((r) => r.size));
    let titleRows = new Set();
    if (body && max >= body * 1.25) {
      const top = rows.filter((r) => r.size >= max - 0.6);
      if (top.length <= 2 && top.reduce((n, r) => n + r.text.length, 0) <= 120) titleRows = new Set(top);
    }

    // Photos go into the reading order above the first line below them.
    const queue = photos
      .filter((ph) => !decoration(ph.sig))
      .map((ph) => {
        const ref = images.length;
        images.push({ ref, blob: ph.blob, width: ph.width, height: ph.height });
        return { ref, cy: ph.cy };
      })
      .sort((a, b) => b.cy - a.cy);
    const marker = (ph) => `{{img:${ph.ref}}}`;
    // The title first: slides often store it after other text on the page.
    const ordered = [...rows.filter((r) => titleRows.has(r)), ...rows.filter((r) => !titleRows.has(r))];
    lines.push(...rowsToLines(ordered, {
      heading: (r) => titleRows.has(r),
      before: (r) => {
        const out = [];
        while (queue.length && queue[0].cy > r.y) out.push(marker(queue.shift()));
        return out;
      },
    }));
    lines.push(...queue.map(marker));
  });
  return { text: lines.join('\n'), images };
}

// Text only (no photos). Kept for tools and tests.
export async function pdfToText(pdfjsLib, data) {
  return (await pdfToSheet(pdfjsLib, data, { withImages: false })).text;
}
