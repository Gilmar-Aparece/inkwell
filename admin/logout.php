<?php
require_once __DIR__ . '/../includes/auth.php';
inkwell_logout_user();
header('Location: /admin/login.php');
exit;
