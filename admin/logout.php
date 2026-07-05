<?php
require_once __DIR__ . '/../includes/store.php';
inkwell_admin_logout();
header('Location: /admin/login.php');
exit;
