<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'DBConnector.php';
include 'query_helpers.php';

// If already logged in, skip the login page
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifier = trim($_POST['log_name'] ?? '');
    $password = $_POST['log_password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = "Invalid username or password";
    } else {
        // Prevent SQL Injection while using your original query_ret function
        $safe_username = mysqli_real_escape_string($conn, $identifier);
        $sql = "SELECT * FROM User WHERE User.user_name = '$safe_username'";
        $user = query_ret($conn, $sql);

        if ($user === false || empty($user)) {
            $error = "Invalid username or password";
        } else {
            // Use password_verify() to check against the secure database hash
            if (password_verify($password, $user[0]['password_hash'])) {

                // Prevent Session Fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user[0]['user_id'];

                header("Location: index.php");
                exit();

            } else {
                $error = "Invalid username or password";
            }
        }
    }
}

// Display and clear flash success message (e.g., from register/logout)
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page auth-page">
        <h1>Log in</h1>
        <p class="auth-sub">Welcome back. Enter your details to see your tasks.</p>

        <?php if ($flash_success): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="login.php">
                <div class="field">
                    <label for="log_name">Username</label>
                    <input id="log_name" name="log_name" type="text"
                           value="<?= htmlspecialchars($identifier) ?>"
                           autocomplete="username" autofocus required>
                </div>

                <div class="field">
                    <label for="log_password">Password</label>
                    <input id="log_password" name="log_password" type="password"
                           autocomplete="current-password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Log in</button>
                </div>
            </form>
        </div>

        <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
    </div>
</body>
</html>