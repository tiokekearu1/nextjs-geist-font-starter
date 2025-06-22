<?php
require_once __DIR__ . '/../../includes/auth.php';

// Perform logout
logoutUser();

// Redirect to login page
header("Location: /modules/users/login.php");
exit;
