<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
logActivity('Admin logged out');
session_destroy();
header('Location: login.php');
exit;
