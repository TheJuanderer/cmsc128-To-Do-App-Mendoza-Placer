<?php

require_once 'DBConnector.php';
require_once 'user_helpers.php';
require_once 'auth.php';

// Protected page: guests are redirected to login.php.
$user = require_login($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page">
        <div class="page-header">
            <h1>Hello, <?= htmlspecialchars($user['user_name']) ?></h1>
        </div>
        <p class="page-meta">You are signed in as <?= htmlspecialchars($user['user_email']) ?>.</p>

        <div class="form-actions">
            <a href="index.php" class="btn btn-outline">Go to the To-Do List</a>

            <!-- Logging out changes state, so it is a POST form, not a plain link -->
            <form method="POST" action="logout.php" style="display:inline;">
                <button type="submit" class="btn btn-primary">Log Out</button>
            </form>
        </div>
    </div>
</body>
</html>
