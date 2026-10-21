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

    // Initialize variables to store form input values and error messages
    $errors = [];
    $title = '';
    $due_date_date = '';
    $due_date_time = '';
    $priority_id = '';
    $category_id = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Retrieve and sanitize form inputs, defaulting to empty strings if not set
        $title = trim($_POST['title'] ?? '');
        $due_date_date = trim($_POST['due_date_date'] ?? '');
        $due_date_time = trim($_POST['due_date_time'] ?? '');
        $priority_id = $_POST['priority_id'] ?? '';
        $category_id = $_POST['category_id'] ?? '';

        // Validate that the task title is not empty
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }

        // Validate 12-hour time format (H:MM AM/PM) via regex
        $time_pattern = '/^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$/';
        $due_date_timestamp = false;

        // Validate the due date and time components
        if ($due_date_date === '') {
            $errors['due_date'] = 'Pick a due date.';
        } elseif ($due_date_time === '') {
            $errors['due_date'] = 'Enter a due time (e.g. 11:59 PM).';
        } elseif (!preg_match($time_pattern, $due_date_time)) {
            $errors['due_date'] = 'Enter a time like 11:59 PM - include AM or PM.';
        } else {
            $due_date_timestamp = strtotime("$due_date_date $due_date_time");
            if ($due_date_timestamp === false) {
                $errors['due_date'] = 'That date/time isn\'t valid.';
            }
        }

        // Ensure the selected priority/category ID is valid
        if (!in_array($priority_id, ['1', '2', '3'], true)) {
            $errors['priority_id'] = 'Please select a priority.';
        }
        if (!in_array($category_id, ['1', '2', '3'], true)) {
            $errors['category_id'] = 'Please select a tag.';
        }

        // If no validation errors occurred, proceed to save the task
        if (empty($errors)) {

            // Format the timestamp into a MySQL-compatible datetime string (YYYY-MM-DD HH:MM:SS)
            $due_date_mysql = date('Y-m-d H:i:s', $due_date_timestamp);

            // Call custom helper function to insert the task into the database
            $ok = insert_task($conn, $user_id, $title, $due_date_mysql, (int) $priority_id, (int) $category_id);

            if ($ok) {
                // Set a success flash message and redirect to the main listing page
                $_SESSION['flash_success'] = 'Task added.';
                header('Location: index.php');
                exit;
            } else {
                // Capture any database insertion errors
                $errors['general'] = 'Could not save the task: ' . $conn->error;
            }
        }
    }

    // Fetch categories and priorities from the database to populate form dropdowns
    $categories = query_ret($conn, "SELECT * FROM `Category` ORDER BY category_id");
    $priorities = query_ret($conn, "SELECT * FROM `Priority` ORDER BY priority_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Task</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page">

        <a href="index.php" class="back-link">&larr; Back to list</a>
        <h1 style="margin-bottom: 24px;">Add task</h1>

        <!-- Global error alert box -->
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <!-- Task Creation form -->
        <div class="form-card">
            <form method="POST" action="create.php">

                <div class="field">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title" value="<?= htmlspecialchars($title) ?>" autofocus>
                    <?php if (!empty($errors['title'])): ?><div class="field-error"><?= $errors['title'] ?></div><?php endif; ?>
                </div>

                <!-- Calendar date picker input -->
                <div class="field">
                    <label for="due_date_date">Due date</label>
                    <input type="date" name="due_date_date" id="due_date_date" value="<?= htmlspecialchars($due_date_date) ?>">
                </div>

                <!-- input due time with proper input checking -->
                <div class="field">
                    <label for="due_date_time">Due time</label>
                    <input
                        type="text"
                        name="due_date_time"
                        id="due_date_time"
                        value="<?= htmlspecialchars($due_date_time) ?>"
                        placeholder="e.g. 11:59 PM"
                        pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?([AaPp][Mm])$"
                        title="Enter a time like 11:59 PM - AM or PM is required."
                        autocomplete="off"
                    >
                    <?php if (!empty($errors['due_date'])): ?><div class="field-error"><?= $errors['due_date'] ?></div><?php endif; ?>
                </div>

                <!-- dynamic priority dropdown -->
                <div class="field">
                    <label for="priority_id">Priority</label>
                    <select name="priority_id" id="priority_id">
                        <option value="">Select priority</option>
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p['priority_id'] ?>" <?= (string) $priority_id === (string) $p['priority_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['priority_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['priority_id'])): ?><div class="field-error"><?= $errors['priority_id'] ?></div><?php endif; ?>
                </div>

                <!-- dynamic category dropdown -->
                <div class="field">
                    <label for="category_id">Tag / category</label>
                    <select name="category_id" id="category_id">
                        <option value="">Select tag</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['category_id'] ?>" <?= (string) $category_id === (string) $c['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['category_id'])): ?><div class="field-error"><?= $errors['category_id'] ?></div><?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add task</button>
                    <a href="index.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</body>
</html>
