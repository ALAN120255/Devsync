<?php
require_once 'includes/config.php';
session_destroy();
header('Location: login.php?logged_out=1');
exit;
?>
