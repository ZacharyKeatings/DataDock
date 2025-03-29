<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

session_unset();
session_destroy();

header("Location: login.php");
exit;
