<?php $__footerUser = current_user(); ?>
  <footer class="site-footer">
    <div class="footer-row">
      <div class="footer-left">
        <div>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. App developed by msv.</div>
      </div>
      <div class="footer-admin">
        <?php if ($__footerUser && ($__footerUser['role'] ?? '') === 'admin'): ?>
          <a href="<?= base_url('admin/index.php') ?>">Admin Dashboard</a>
          <span>·</span>
          <a href="<?= base_url('admin/logout.php') ?>">Sign out</a>
        <?php else: ?>
          <a href="<?= base_url('admin/login.php') ?>">Admin</a>
        <?php endif; ?>
      </div>
    </div>
  </footer>

  <div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" id="modalContent"></div>
  </div>

  <script src="<?= base_url('assets/js/app.js') ?>"></script>

  <?php if (bot_is_ready()): $__bot = bot_settings(); ?>
  <div class="bot" id="bot" data-api="<?= h(base_url('bot_api.php')) ?>">
    <button type="button" class="bot-launcher" aria-controls="botPanel" aria-expanded="false" aria-label="Open <?= h($__bot['name']) ?> chat">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 01-11.6 7.1L4 20l1.1-4.6A8 8 0 1121 12z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/></svg>
      <span class="bot-launcher-label"><?= h($__bot['name']) ?></span>
    </button>
    <section class="bot-panel" id="botPanel" role="dialog" aria-label="<?= h($__bot['name']) ?>" hidden>
      <header class="bot-head">
        <div class="bot-avatar" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 14h.01M15 14h.01"/><circle cx="12" cy="3.5" r="1"/></svg>
        </div>
        <div class="bot-title">
          <strong><?= h($__bot['name']) ?></strong>
          <span>Ask me about <?= h($__bot['topic']) ?></span>
        </div>
        <button type="button" class="bot-close" aria-label="Close chat">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </header>
      <div class="bot-messages" aria-live="polite"></div>
      <form class="bot-form" autocomplete="off">
        <input class="bot-input" type="text" name="q" maxlength="500" dir="auto" placeholder="Ask a question…" aria-label="Your question">
        <button class="bot-send" type="submit" aria-label="Send">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </form>
    </section>
  </div>
  <script src="<?= base_url('assets/js/bot.js') ?>" defer></script>
  <?php endif; ?>
</body>
</html>
