<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user($conn) {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return find_user_by_id($conn, (int) $_SESSION['user_id']);
}

// Stops the browser from showing a cached protected page after logout (back button).
function no_cache() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Call at the top of a protected page. Guests are sent to the login page.
function require_login($conn) {
    no_cache();
    $user = current_user($conn);
    if ($user === null) {
        unset($_SESSION['user_id']);   // stale session/account deleted
        header('Location: login.php');
        exit();
    }
    return $user;
}

// Login for already registered
function redirect_if_logged_in($conn) {
    if (current_user($conn) !== null) {
        header('Location: login_test_page.php');
        exit();
    }
}

?>
