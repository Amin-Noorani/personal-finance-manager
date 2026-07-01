<?php
session_start();
session_unset();
session_destroy();
header('Location: /pfm/login.php');
exit;
