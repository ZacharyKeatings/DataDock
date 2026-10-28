<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/themes.php';
datadock_clear_theme_cookie();

session_unset();
session_destroy();

header("Location: login.php");
exit;
