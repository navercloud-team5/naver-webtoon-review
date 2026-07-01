<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/db_session.php';
session_destroy();
header("Location: /index.php");
exit;
?>
