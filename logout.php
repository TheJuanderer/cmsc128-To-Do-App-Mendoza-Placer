<?php

require_once 'DBConnector.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];          // forget who was logged in
    session_destroy();       // delete the server-side session
    session_start();         // brand-new empty session
    session_regenerate_id(true);
    $_SESSION['flash_success'] = 'You have been logged out.';
}

header("Location: login.php");
exit();
