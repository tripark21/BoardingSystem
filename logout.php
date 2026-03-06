<?php
require_once 'config/database.php';
session_destroy();
header("Location: /boarding_system/login.php");
exit;
?>