<?php

// Find an active user by username OR email.
function find_user_by_login($conn, $identifier) {
    try {
        $sql = "SELECT user_id, user_name, user_email, password_hash
                FROM `User`
                WHERE (user_name = ? OR user_email = ?) AND deleted_at IS NULL
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (mysqli_sql_exception $e) {
        error_log('find_user_by_login: ' . $e->getMessage());
        return null;
    }
}

// Find an active user by ID.
function find_user_by_id($conn, $user_id) {
    try {
        $sql = "SELECT user_id, user_name, user_email
                FROM `User`
                WHERE user_id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (mysqli_sql_exception $e) {
        error_log('find_user_by_id: ' . $e->getMessage());
        return null;
    }
}

// Soft-deleted rows count too, because the UNIQUE keys still apply to them.
function find_user_conflicts($conn, $name, $email) {
    $taken = ['name' => false, 'email' => false];
    try {
        $stmt = $conn->prepare("SELECT user_name, user_email FROM `User` WHERE user_name = ? OR user_email = ?");
        if ($stmt === false) {
            return $taken;
        }
        $stmt->bind_param("ss", $name, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (strcasecmp($row['user_name'], $name) === 0)   { $taken['name'] = true; }
            if (strcasecmp($row['user_email'], $email) === 0) { $taken['email'] = true; }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('find_user_conflicts: ' . $e->getMessage());
    }
    return $taken;
}

// Insert a new user  / password hashing
function create_user($conn, $name, $email, $password_hash) {
    try {
        $sql = "INSERT INTO `User` (`user_name`, `user_email`, `password_hash`) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param("sss", $name, $email, $password_hash);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (mysqli_sql_exception $e) {
        // two people register the same name at the same instant
        error_log('create_user: ' . $e->getMessage());
        return false;
    }
}

?>
