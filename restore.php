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

    // Ensure the script is only accessed via POST requests to prevent direct URL access
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }

    // Retrieve and sanitize the task ID from the POST request, defaulting to 0
    $task_id = intval($_POST['task_id'] ?? 0);
    restore_task($conn, $task_id, $user_id);

    // Set a session flash message to display a success notification on the main page
    $_SESSION['flash_success'] = 'Task restored.';

    header('Location: index.php');
    exit;
?>
