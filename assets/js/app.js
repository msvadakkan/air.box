(function () {
  'use strict';

  // ---- Theme toggle (persisted; falls back to OS preference via CSS) ----
  function currentTheme() {
    var explicit = document.documentElement.getAttribute('data-theme');
    if (explicit) return explicit;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
  }
  document.querySelectorAll('.theme-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
  });

  // ---- Mobile nav drawer ----
  var navToggle = document.getElementById('navToggle');
  var navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      navLinks.classList.toggle('open');
    });
    navLinks.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') navLinks.classList.remove('open');
    });
  }

  // ---- Admin upload forms: real progress bar via XHR ----
  // A plain <form method=post> gives the browser no way to show upload
  // progress, so this intercepts submit, sends the same multipart body via
  // XHR (which exposes upload.onprogress), and once the response lands
  // (whether that's the success redirect PHP sends or a validation-error
  // page re-rendered at the same URL) swaps it in as if it were a normal
  // navigation.
  document.querySelectorAll('form.upload-form').forEach(function (form) {
    var progress = form.querySelector('.upload-progress');
    var bar = form.querySelector('.upload-progress-bar');
    var text = form.querySelector('.upload-progress-text');
    var submitBtn = form.querySelector('button[type=submit]');
    if (!progress || !bar || !text) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      progress.hidden = false;
      progress.classList.remove('error');
      bar.style.width = '0%';
      text.textContent = 'Uploading… 0%';
      if (submitBtn) submitBtn.disabled = true;

      // Not form.action: these forms have <input name="action">, and per
      // the HTML spec a form's named controls shadow same-named built-in
      // properties — form.action resolves to that <input> element, not the
      // submit URL. None of these forms set an explicit action attribute
      // (they all post to their own page), so the document URL is correct.
      var xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);

      xhr.upload.addEventListener('progress', function (evt) {
        if (!evt.lengthComputable) return;
        var pct = Math.round((evt.loaded / evt.total) * 100);
        bar.style.width = pct + '%';
        text.textContent = pct < 100
          ? 'Uploading… ' + pct + '%'
          : 'Upload complete — processing…';
      });

      xhr.addEventListener('load', function () {
        // Swap the whole document for the server's response, whatever it
        // is (success banner after PHP's redirect, or the same page
        // re-rendered with validation errors) — matches what a normal
        // form submission would have shown.
        document.open();
        document.write(xhr.responseText);
        document.close();
        if (xhr.responseURL) {
          window.history.replaceState(null, '', xhr.responseURL);
        }
      });

      xhr.addEventListener('error', function () {
        progress.classList.add('error');
        text.textContent = 'Upload failed — check your connection and try again.';
        if (submitBtn) submitBtn.disabled = false;
      });

      xhr.send(new FormData(form));
    });
  });

  // ---- Admin tables: select-all + bulk delete button state ----
  document.querySelectorAll('form.bulk-form').forEach(function (form) {
    var selectAll = form.querySelector('.select-all');
    var bulkBtn = form.querySelector('.bulk-delete-btn');
    var countEl = form.querySelector('.bulk-count');
    var rowChecks = form.querySelectorAll('.row-check');

    function refresh() {
      var checked = form.querySelectorAll('.row-check:checked').length;
      if (bulkBtn) bulkBtn.disabled = checked === 0;
      if (countEl) countEl.textContent = checked;
      if (selectAll) {
        selectAll.checked = checked > 0 && checked === rowChecks.length;
        selectAll.indeterminate = checked > 0 && checked < rowChecks.length;
      }
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        rowChecks.forEach(function (cb) { cb.checked = selectAll.checked; });
        refresh();
      });
    }
    rowChecks.forEach(function (cb) { cb.addEventListener('change', refresh); });
    refresh();
  });

  // ---- Admin movies: toggle "featured" straight from the list ----
  document.querySelectorAll('.featured-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var checked = cb.checked;
      cb.disabled = true;
      var body = new URLSearchParams();
      body.set('toggle_featured', cb.dataset.id);
      body.set('value', checked ? '1' : '0');
      fetch(window.location.href, { method: 'POST', body: body })
        .then(function (r) { if (!r.ok) throw new Error('request failed'); return r.json(); })
        .then(function (data) {
          if (!data.ok) throw new Error('update failed');
          cb.disabled = false;
        })
        .catch(function () {
          cb.checked = !checked; // revert on failure
          cb.disabled = false;
          alert('Could not update featured status. Please try again.');
        });
    });
  });

  // ---- Admin sidebar drawer ----
  var adminToggle = document.getElementById('adminSidebarToggle');
  var adminSidebar = document.getElementById('adminSidebar');
  var adminBackdrop = document.getElementById('adminBackdrop');
  if (adminToggle && adminSidebar && adminBackdrop) {
    var closeSidebar = function () {
      adminSidebar.classList.remove('open');
      adminBackdrop.classList.remove('open');
    };
    adminToggle.addEventListener('click', function () {
      adminSidebar.classList.toggle('open');
      adminBackdrop.classList.toggle('open');
    });
    adminBackdrop.addEventListener('click', closeSidebar);
    adminSidebar.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') closeSidebar();
    });
  }

  // ---- Navbar solidify on scroll ----
  var navbar = document.getElementById('navbar');
  function onScroll() {
    if (!navbar) return;
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  }
  window.addEventListener('scroll', onScroll);
  onScroll();

  // ---- Row horizontal scroll arrows ----
  document.querySelectorAll('.row-wrap').forEach(function (wrap) {
    var track = wrap.querySelector('.row-track');
    var left = wrap.querySelector('.row-arrow.left');
    var right = wrap.querySelector('.row-arrow.right');
    if (!track) return;
    var step = function () { return track.clientWidth * 0.8; };
    if (left) left.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
    if (right) right.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
  });

  // ---- Card modal ----
  var backdrop = document.getElementById('modalBackdrop');
  var modalContent = document.getElementById('modalContent');

  function openModal(card) {
    if (!modalContent) return;
    var d = card.dataset;
    modalContent.innerHTML =
      '<div class="modal-banner" style="background-image:url(\'' + d.image + '\')">' +
        '<button class="modal-close" id="modalCloseBtn">&times;</button>' +
      '</div>' +
      '<div class="modal-body">' +
        '<h3>' + escapeHtml(d.title) + '</h3>' +
        '<div class="modal-meta">' + escapeHtml(d.meta || '') + '</div>' +
        '<p class="modal-desc">' + escapeHtml(d.desc || '') + '</p>' +
        '<a class="btn btn-accent" href="' + d.link + '">' + escapeHtml(d.action || 'Open') + '</a>' +
      '</div>';
    backdrop.classList.add('open');
    document.getElementById('modalCloseBtn').addEventListener('click', closeModal);
  }
  function closeModal() { backdrop.classList.remove('open'); }
  if (backdrop) {
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) closeModal();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  document.querySelectorAll('.card[data-modal]').forEach(function (card) {
    card.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(card);
    });
  });

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.innerText = str || '';
    return div.innerHTML;
  }

  // ---- Live search across cards ----
  var searchInput = document.getElementById('siteSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('.card').forEach(function (card) {
        var title = (card.dataset.title || '').toLowerCase();
        var extra = (card.dataset.meta || '').toLowerCase();
        var match = !q || title.indexOf(q) !== -1 || extra.indexOf(q) !== -1;
        card.style.display = match ? '' : 'none';
      });
      document.querySelectorAll('.row-section').forEach(function (section) {
        var anyVisible = Array.prototype.some.call(
          section.querySelectorAll('.card'),
          function (c) { return c.style.display !== 'none'; }
        );
        section.style.display = anyVisible ? '' : 'none';
      });
    });
  }

  // ---- Hero rotate (if multiple hero slides supplied) ----
  var heroEl = document.querySelector('.hero[data-rotate]');
  if (heroEl) {
    var slides = JSON.parse(heroEl.dataset.rotate || '[]');
    var heroContent = heroEl.querySelector('.hero-content');
    var idx = 0;
    if (slides.length > 1) {
      setInterval(function () {
        if (heroContent) heroContent.classList.add('swap');
        setTimeout(function () {
          idx = (idx + 1) % slides.length;
          var s = slides[idx];
          heroEl.style.backgroundImage = "url('" + s.image + "')";
          var badge = heroEl.querySelector('.hero-badge');
          var title = heroEl.querySelector('.hero-title');
          var desc = heroEl.querySelector('.hero-desc');
          var play = heroEl.querySelector('.hero-play');
          var info = heroEl.querySelector('.hero-info');
          if (badge) badge.textContent = s.badge;
          if (title) title.textContent = s.title;
          if (desc) desc.textContent = s.desc;
          if (play) play.href = s.link;
          if (info) {
            info.dataset.title = s.title;
            info.dataset.desc = s.desc;
            info.dataset.image = s.image;
            info.dataset.link = s.link;
            info.dataset.meta = s.meta;
          }
          if (heroContent) heroContent.classList.remove('swap');
        }, 350);
      }, 7000);
    }
  }

  // ---- Fade images in as they finish loading ----
  document.querySelectorAll('img.fade-in').forEach(function (img) {
    if (img.complete && img.naturalWidth > 0) {
      img.classList.add('loaded');
    } else {
      img.addEventListener('load', function () { img.classList.add('loaded'); });
      img.addEventListener('error', function () { img.classList.add('loaded'); });
    }
  });
})();
