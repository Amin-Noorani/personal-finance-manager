<?php
require_once __DIR__ . '/config/auth.php';
clearRememberCookie();
session_unset();
session_destroy();
header('Location: /pfm/login.php');
exit;
