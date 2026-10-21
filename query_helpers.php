
<?php 
    include 'DBConnector.php';

    //this returns an array of what you queried in SQL
    function query_ret($conn, $sql) {
        $result = $conn->query($sql);

        if ($result === false) {
            return false;
        }

        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    //executing a query where you dont expect an object to be returned, like when deleting, or inserting
    function query_insert($conn, $query) {
        $result = $conn->query($query);

        if ($result === false) {
            return false;
        }

        return $result;
    }

    function insert_user($conn, $query) {
        $result = query_insert($conn, $query);
        return $result;
    }



    // function used for Creating, Updating and Deleting tasks
    function execute ($conn, $sql) {
        $result = $conn->query($sql);
        return $result !== false;
    }

    // Inserts a new task for a specific user.
function insert_task($conn, $user_id, $title, $due_date, $priority_id, $category_id) {
    $sql = "INSERT INTO `Task` (`user_id`, `title`, `due_date`, `priority_id`, `category_id`)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    // "issii" = int, string, string, int, int
    $stmt->bind_param("issii", $user_id, $title, $due_date, $priority_id, $category_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Retrieves a single active task, only if it belongs to the user.
function get_task($conn, $task_id, $user_id) {
    $sql = "SELECT * FROM `Task`
            WHERE `task_id` = ? AND `user_id` = ? AND `deleted_at` IS NULL";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    return $task ?: null;
}

// Updates a task, only if it belongs to the user.
function update_task($conn, $task_id, $user_id, $title, $due_date, $priority_id, $category_id) {
    $sql = "UPDATE `Task`
            SET `title` = ?, `due_date` = ?, `priority_id` = ?, `category_id` = ?
            WHERE `task_id` = ? AND `user_id` = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    // "ssiiii" = string, string, int, int, int, int
    $stmt->bind_param("ssiiii", $title, $due_date, $priority_id, $category_id, $task_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Soft delete, only if the task belongs to the user.
function soft_delete_task($conn, $task_id, $user_id) {
    $sql = "UPDATE `Task` SET `deleted_at` = NOW()
            WHERE `task_id` = ? AND `user_id` = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param("ii", $task_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Restores a soft-deleted task, only if it belongs to the user.
function restore_task($conn, $task_id, $user_id) {
    $sql = "UPDATE `Task` SET `deleted_at` = NULL
            WHERE `task_id` = ? AND `user_id` = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param("ii", $task_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Toggles is_done, only if the task belongs to the user.
function toggle_task_done($conn, $task_id, $user_id) {
    $sql = "UPDATE `Task` SET `is_done` = NOT `is_done`
            WHERE `task_id` = ? AND `user_id` = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param("ii", $task_id, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}


?>