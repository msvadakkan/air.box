<?php
require __DIR__ . '/../includes/config.php';
logout_user();
redirect(base_url('admin/login.php'));
