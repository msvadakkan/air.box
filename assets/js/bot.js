// Rotana Bot chat window. The markup comes from includes/footer.php (only
// rendered once an answer sheet has been imported); answers come from
// bot_api.php. The conversation is kept in sessionStorage so it survives
// moving between pages, and cleared when the browser tab closes.
(function () {
  'use strict';

  var root = document.getElementById('bot');
  if (!root) return;

  var api = root.getAttribute('data-api');
  var launcher = root.querySelector('.bot-launcher');
  var panel = root.querySelector('.bot-panel');
  var closeBtn = root.querySelector('.bot-close');
  var log = root.querySelector('.bot-messages');
  var form = root.querySelector('.bot-form');
  var input = root.querySelector('.bot-input');
  var sendBtn = root.querySelector('.bot-send');

  var STORE = 'airbox-bot';
  var MAX_SAVED = 40;
  var history = [];
  var started = false;
  var busy = false;

  try { history = JSON.parse(sessionStorage.getItem(STORE) || '[]') || []; } catch (e) { history = []; }

  function save() {
    try { sessionStorage.setItem(STORE, JSON.stringify(history.slice(-MAX_SAVED))); } catch (e) { /* private mode */ }
  }

  function scrollDown() { log.scrollTop = log.scrollHeight; }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  // Full-screen view of a photo; tap anywhere or press Escape to close.
  var viewer = null;
  function openPhoto(src) {
    viewer = el('div', 'bot-viewer');
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-label', 'Photo');
    var img = el('img');
    img.src = src;
    img.alt = '';
    var x = el('button', 'bot-viewer-close');
    x.type = 'button';
    x.setAttribute('aria-label', 'Close photo');
    x.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    viewer.appendChild(img);
    viewer.appendChild(x);
    viewer.addEventListener('click', closePhoto);
    root.appendChild(viewer);
    x.focus();
  }
  function closePhoto() {
    if (!viewer) return;
    viewer.remove();
    viewer = null;
  }

  function render(msg) {
    var row = el('div', 'bot-msg bot-msg-' + msg.from);
    var bubble = el('div', 'bot-bubble');
    bubble.setAttribute('dir', 'auto');
    bubble.appendChild(el('div', 'bot-text', msg.text));
    if (msg.images && msg.images.length) {
      var grid = el('div', 'bot-images' + (msg.images.length === 1 ? ' bot-images-one' : ''));
      msg.images.forEach(function (src) {
        var btn = el('button', 'bot-photo');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'View photo larger');
        var img = el('img');
        img.src = src;
        img.alt = '';
        img.loading = 'lazy';
        // Keep the newest message in view as its photos load in.
        img.addEventListener('load', function () { if (row === log.lastElementChild) scrollDown(); });
        btn.appendChild(img);
        btn.addEventListener('click', function () { openPhoto(src); });
        grid.appendChild(btn);
      });
      bubble.appendChild(grid);
    }
    row.appendChild(bubble);

    if (msg.suggestions && msg.suggestions.length) {
      var wrap = el('div', 'bot-suggest');
      if (msg.label) wrap.appendChild(el('div', 'bot-suggest-label', msg.label));
      msg.suggestions.forEach(function (q) {
        var chip = el('button', 'bot-chip', q);
        chip.type = 'button';
        chip.setAttribute('dir', 'auto');
        chip.addEventListener('click', function () { ask(q); });
        wrap.appendChild(chip);
      });
      row.appendChild(wrap);
    }
    log.appendChild(row);
    scrollDown();
  }

  function add(msg) {
    history.push(msg);
    save();
    render(msg);
  }

  function showTyping() {
    var row = el('div', 'bot-msg bot-msg-bot bot-typing-row');
    row.appendChild(el('div', 'bot-bubble bot-typing'));
    row.firstChild.innerHTML = '<span></span><span></span><span></span>';
    row.setAttribute('aria-label', 'Typing');
    log.appendChild(row);
    scrollDown();
    return row;
  }

  function setBusy(on) {
    busy = on;
    sendBtn.disabled = on;
  }

  function ask(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    add({ from: 'user', text: text });
    input.value = '';
    setBusy(true);
    var typing = showTyping();

    var body = new URLSearchParams();
    body.set('q', text);
    fetch(api, { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (res) {
        typing.remove();
        add({ from: 'bot', text: res.text, images: res.images || [], suggestions: res.suggestions || [], label: res.suggestions_label || '' });
      })
      .catch(function () {
        typing.remove();
        add({ from: 'bot', text: "Sorry, I couldn't connect just now. Please try again." });
      })
      .then(function () {
        setBusy(false);
        if (window.matchMedia('(min-width: 641px)').matches) input.focus();
      });
  }

  function start() {
    started = true;
    if (history.length) {
      history.forEach(render);
      return;
    }
    var typing = showTyping();
    fetch(api, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (res) {
        typing.remove();
        add({ from: 'bot', text: res.welcome, suggestions: res.suggestions || [], label: res.suggestions_label || '' });
      })
      .catch(function () {
        typing.remove();
        add({ from: 'bot', text: 'Hi! Ask me a question.' });
      });
  }

  function open() {
    panel.hidden = false;
    root.classList.add('open');
    launcher.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('bot-open');
    if (!started) start();
    scrollDown();
    // On phones, focusing would pop the keyboard over the welcome message.
    if (window.matchMedia('(min-width: 641px)').matches) input.focus();
  }

  function close() {
    panel.hidden = true;
    root.classList.remove('open');
    launcher.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('bot-open');
    launcher.focus();
  }

  launcher.addEventListener('click', function () { panel.hidden ? open() : close(); });
  closeBtn.addEventListener('click', close);
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    ask(input.value);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (viewer) closePhoto();
    else if (!panel.hidden) close();
  });
})();
