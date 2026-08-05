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
