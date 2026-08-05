<?php $__footerUser = current_user(); ?>
  <footer class="site-footer">
    <div class="footer-row">
      <div class="footer-left">
        <div>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. Developed for Rotana Jet customer entertainment.</div>
        <div class="footer-powered">Powered by <a href="https://rotanajet.com/" target="_blank" rel="noopener">Rotana Jet</a></div>
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
</body>
</html>
