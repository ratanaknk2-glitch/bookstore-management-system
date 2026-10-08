<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();
$_SESSION = [];
session_unset();
session_destroy();
jsonResponse(true, null, 'Logged out successfully.');
