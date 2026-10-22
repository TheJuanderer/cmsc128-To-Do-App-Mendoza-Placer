<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    include 'DBConnector.php';
    include 'query_helpers.php';

    // Must be logged in to see tasks
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = (int) $_SESSION['user_id'];
    

    // filters (whitelisted / cast to int, safe to concatenate)
    $category_id = isset($_GET['category_id']) && $_GET['category_id'] !== ''
        ? intval($_GET['category_id'])
        : null;

    $priority_id = isset($_GET['priority_id']) && $_GET['priority_id'] !== ''
        ? intval($_GET['priority_id'])
        : null;

    $sort_options = [
        'due_date'   => 'Task.due_date ASC',
        'date_added' => 'Task.created_at DESC',
        'priority'   => 'Task.priority_id DESC',
        'category'   => 'Task.category_id ASC',
    ];
    $sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_options)
        ? $_GET['sort']
        : 'due_date';

    $where = "WHERE Task.deleted_at IS NULL AND Task.user_id = $user_id";
    if ($category_id !== null) {
        $where .= " AND Task.category_id = $category_id";
    }
    if ($priority_id !== null) {
        $where .= " AND Task.priority_id = $priority_id";
    }

    $sql = "
        SELECT
            Task.task_id,
            Task.title,
            Task.due_date,
            Task.is_done,
            Task.created_at,
            Priority.priority_id,
            Priority.priority_name,
            Category.category_id,
            Category.category_name
        FROM Task
        JOIN Priority ON Task.priority_id = Priority.priority_id
        JOIN Category ON Task.category_id = Category.category_id
        $where
        ORDER BY {$sort_options[$sort]}
    ";

    $tasks = query_ret($conn, $sql);

    if ($tasks === false) {
        die("Error fetching tasks: " . $conn->error);
    }

    // Fetch categories and priorities for the dropdown menus
    $categories = query_ret($conn, "SELECT * FROM `Category` ORDER BY category_id");
    $priorities = query_ret($conn, "SELECT * FROM `Priority` ORDER BY priority_id");

    // Calculate counts for open vs. done tasks
    $open_count = 0;
    $done_count = 0;
    foreach ($tasks as $t) {
        if ($t['is_done']) { $done_count++; } else { $open_count++; }
    }

    // flash message set by create.php / edit.php / delete.php / restore.php
    $flash_success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    // undo-delete info set by delete.php, read once here
    $undo_task_id = $_SESSION['undo_task_id'] ?? null;
    $undo_task_title = $_SESSION['undo_task_title'] ?? null;
    unset($_SESSION['undo_task_id'], $_SESSION['undo_task_title']);

    function tag_class($name) {
        return strtolower($name);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>To-Do List</title>
</head>
<body>
    <div class="page">
        <div class="page-header">

            <h1>To-Do List</h1>
            
            <!-- add task button -->
            <a href="create.php" class="btn btn-primary">+ Add task</a>

        </div>
        <p class="page-meta"><?= $open_count ?> open &middot; <?= $done_count ?> done</p>

        <!-- Flash success message notification -->
        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <!-- sorting options -->
        <form method="GET" action="index.php" class="toolbar">
            <span class="toolbar-group">
                <label for="category_id">Tag</label>
                <select name="category_id" id="category_id" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>" <?= $category_id === (int) $c['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </span>

            <span class="toolbar-group">
                <label for="priority_id">Priority</label>
                <select name="priority_id" id="priority_id" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p['priority_id'] ?>" <?= $priority_id === (int) $p['priority_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['priority_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </span>

            <div class="spacer"></div>

            <span class="toolbar-group">
                <label for="sort">Sort by</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due date</option>
                    <option value="date_added" <?= $sort === 'date_added' ? 'selected' : '' ?>>Date added</option>
                    <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                    <option value="category" <?= $sort === 'category' ? 'selected' : '' ?>>Tag</option>
                </select>
            </span>
        </form>

        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <h2>Nothing here</h2>
                <p>Add a task, or clear your filters above.</p>
            </div>

        <?php else: ?>
            <ul class="task-list">
                <?php $previous_date = null; ?>
                <?php foreach ($tasks as $task): ?>
                    <?php
                        // Check if task is past due and still open
                        $is_overdue = !$task['is_done'] && strtotime($task['due_date']) < time();
                        $priority_class = tag_class($task['priority_name']) === 'med' ? 'med' : tag_class($task['priority_name']);
                        $category_class = tag_class($task['category_name']);

                        // Check for date boundaries to visually group tasks if needed
                        $task_date = date('Y-m-d', strtotime($task['due_date']));
                        $is_new_date = $previous_date !== null && $task_date !== $previous_date;
                        $previous_date = $task_date;

                        $row_classes = trim(
                            ($task['is_done'] ? 'is-done' : '') . ' ' .
                            ($is_new_date ? 'date-boundary' : '')
                        );
                    ?>
                    
                    <!-- checkbox status and actions -->
                    <li class="task-row <?= $row_classes ?>">
                        <form method="POST" action="toggle.php">
                            <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                            <input
                                type="checkbox"
                                class="task-check"
                                onchange="this.form.submit()"
                                <?= $task['is_done'] ? 'checked' : '' ?>
                                aria-label="Mark '<?= htmlspecialchars($task['title']) ?>' as done"
                            >
                        </form>

                        <!-- display appropriate tags -->
                        <div class="task-body">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="task-meta">
                                <span class="due-date <?= $is_overdue ? 'overdue' : '' ?>">
                                    <?= $is_overdue ? 'Overdue: ' : 'Due ' ?><?= date('M j, g:i A', strtotime($task['due_date'])) ?>
                                </span>
                                <span class="tag tag-<?= $priority_class ?>"><?= htmlspecialchars($task['priority_name']) ?></span>
                                <span class="tag tag-<?= $category_class ?>"><?= htmlspecialchars($task['category_name']) ?></span>
                            </div>
                        </div>

                        <!-- edit and delete buttons -->
                        <div class="task-actions">
                            <a href="edit.php?id=<?= $task['task_id'] ?>" class="btn btn-text btn-sm">Edit</a>
                            <button
                                type="button"
                                class="btn btn-danger-text btn-sm"
                                onclick="openDeleteModal('<?= $task['task_id'] ?>', '<?= htmlspecialchars(addslashes($task['title']), ENT_QUOTES) ?>')"
                            >Delete</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- Delete confirmation modal -->
        <div class="modal-backdrop" id="delete-modal">
            <div class="modal-box">
                <h3>Delete this task?</h3>
                <p id="delete-modal-text"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                    <form method="POST" action="delete.php" id="delete-modal-form">
                        <input type="hidden" name="task_id" id="delete-modal-task-id" value="">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- undo toast notification -->
        <?php if ($undo_task_id): ?>
            <div class="toast" id="undo-toast">
                <?php
                    $short_title = strlen($undo_task_title) > 30
                        ? substr($undo_task_title, 0, 30) . '...'
                        : $undo_task_title;
                ?>
                <span>Deleted "<?= htmlspecialchars($short_title) ?>"</span>
                <form method="POST" action="restore.php">
                    <input type="hidden" name="task_id" value="<?= $undo_task_id ?>">
                    <button type="submit">Undo</button>
                </form>
                <div class="toast-bar"></div>
            </div>
        <?php endif; ?>

        <script>
            function openDeleteModal(id, title) {
                document.getElementById('delete-modal-text').textContent =
                    'This will remove "' + title + '" from your list. You can undo right after.';
                document.getElementById('delete-modal-task-id').value = id;
                document.getElementById('delete-modal').classList.add('is-open');
            }
            function closeDeleteModal() {
                document.getElementById('delete-modal').classList.remove('is-open');
            }

            // Auto-dismiss the undo toast notification after 5 seconds
            const toast = document.getElementById('undo-toast');
            if (toast) {
                setTimeout(() => toast.remove(), 5000);
            }
        </script>

    </div>
</body>
</html>