<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    include_once 'DBConnector.php';
    include_once 'query_helpers.php';

    // Must be logged in
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

    // Only finds the task if it belongs to this user
    $task = get_task($conn, $task_id, $user_id);

    if ($task) {
        soft_delete_task($conn, $task_id, $user_id);

        // read once by index.php to render the undo'd task
        $_SESSION['undo_task_id'] = $task_id;
        $_SESSION['undo_task_title'] = $task['title'];
        $_SESSION['flash_success'] = 'Task deleted.';
    }

    header('Location: index.php');
    exit;
?>