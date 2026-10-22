<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    include_once 'DBConnector.php';
    include_once 'query_helpers.php';

    // Must be logged in to add tasks
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }

    $task_id = intval($_POST['task_id'] ?? 0);
    toggle_task_done($conn, $task_id, $user_id);

    // send the user back to whatever filtered/sorted view they were on
    $back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $back);
    exit;
?>
