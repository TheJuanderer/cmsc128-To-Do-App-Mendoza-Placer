<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'DBConnector.php';
require_once 'user_helpers.php';
require_once 'auth.php';


 // Validates user input format constraints for usernames.
function validate_username(string $v): ?string {
    if ($v === '') return 'Please enter a username.';
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v)) {
        return 'Username must be 3-30 characters long (letters, numbers, or underscores only).';
    }
    return null;
}

// Validates email structure using PHP built-in filters.
function validate_email_address(string $v): ?string {
    if ($v === '') return 'Please enter an email address.';
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address (e.g., user@example.com).';
    }
    return null;
}


//Enforces minimum security requirements for new passwords.
function validate_new_password(string $v): ?string {
    if ($v === '') return 'Please enter a password.';
    if (strlen($v) < 8) return 'Password must be at least 8 characters long.';
    return null;
}

// Checks for username or email conflicts while excluding the active user's record.
function profile_find_user_conflicts($conn, $name, $email, $exclude_user_id) {
    $taken = ['name' => false, 'email' => false];
    try {
        $stmt = $conn->prepare("SELECT user_name, user_email FROM `User` WHERE (user_name = ? OR user_email = ?) AND user_id <> ?");
        if ($stmt === false) return $taken;
        $stmt->bind_param("ssi", $name, $email, $exclude_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (strcasecmp($row['user_name'], $name) === 0)   { $taken['name'] = true; }
            if (strcasecmp($row['user_email'], $email) === 0) { $taken['email'] = true; }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('profile_find_user_conflicts: ' . $e->getMessage());
    }
    return $taken;
}

// Executes a secure database update for user profile attributes.
function profile_update_account($conn, $user_id, $name, $email) {
    $stmt = $conn->prepare("UPDATE `User` SET user_name = ?, user_email = ? WHERE user_id = ? AND deleted_at IS NULL");
    if ($stmt === false) return false;
    $stmt->bind_param("ssi", $name, $email, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Retrieves the hashed password for verification.
function profile_get_password_hash($conn, $user_id) {
    $stmt = $conn->prepare("SELECT password_hash FROM `User` WHERE user_id = ? AND deleted_at IS NULL");
    if ($stmt === false) return null;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['password_hash'] ?? null;
}

// Updates the user password hash securely
function profile_update_password($conn, $user_id, $password_hash) {
    $stmt = $conn->prepare("UPDATE `User` SET password_hash = ? WHERE user_id = ? AND deleted_at IS NULL");
    if ($stmt === false) return false;
    $stmt->bind_param("si", $password_hash, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Authenticate session state
$user    = require_login($conn);
$user_id = (int) $user['user_id'];

$acct_errors = [];
$pw_errors   = [];
$username = $user['user_name'];
$email    = $user['user_email'];

// Clear old form errors and submitted values after a refresh redirect.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acct_errors = [];
    $pw_errors = [];

    $username = $user['user_name'];
    $email = $user['user_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Process Account Details Form
    if ($action === 'account') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (($msg = validate_username($username)) !== null)   { $acct_errors['name'] = $msg; }
        if (($msg = validate_email_address($email)) !== null) { $acct_errors['email'] = $msg; }

        if (empty($acct_errors)) {
            $taken = profile_find_user_conflicts($conn, $username, $email, $user_id);
            if ($taken['name'])  { $acct_errors['name']  = 'This username is already taken. Please choose another.'; }
            if ($taken['email']) { $acct_errors['email'] = 'An account with this email address already exists.'; }
        }

        if (empty($acct_errors)) {
            if (profile_update_account($conn, $user_id, $username, $email)) {
                $_SESSION['flash_success'] = 'Profile updated successfully.';
                header('Location: profile.php');
                exit;
            }
            $acct_errors['general'] = 'Could not update your profile. Please try again.';
        }
    }

    // Process Password Change Form
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stored_hash = profile_get_password_hash($conn, $user_id);
        if ($current === '' || !password_verify($current, (string) $stored_hash)) {
            $pw_errors['current'] = 'Your current password is incorrect.';
        }
        if ($new !== '' && password_verify($new, (string) $stored_hash)) {
            $pw_errors['new'] = 'Your new password must be different from your current password.';
        }
        if (($msg = validate_new_password($new)) !== null) { $pw_errors['new'] = $msg; }
        if ($confirm === '') {
            $pw_errors['confirm'] = 'Please confirm your new password.';
        } elseif ($new !== $confirm && !isset($pw_errors['new'])) {
            $pw_errors['confirm'] = 'Passwords do not match. Please re-enter.';
        }

        if (empty($pw_errors)) {
            if (profile_update_password($conn, $user_id, password_hash($new, PASSWORD_DEFAULT))) {
                // Terminate session securely and force re-authentication
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                
                // Redirect user to login with success indicator
                header('Location: login.php?password_changed=1');
                exit;
            }
            $pw_errors['general'] = 'Could not change your password. Please try again.';
        }
    }
}

$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="page profile-page">
        <a href="index.php" class="back-link">&larr; Back to list</a>
        <h1 class="form-title">Profile</h1>

        <?php if ($flash_success): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <h2 class="section-title">Account details</h2>
        <div class="form-card">
            <form method="POST" action="profile.php" novalidate>
                <input type="hidden" name="action" value="account">
                <?php if (!empty($acct_errors['general'])): ?>
                    <div class="alert alert-error" role="alert"><?= htmlspecialchars($acct_errors['general']) ?></div>
                <?php endif; ?>

                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?= htmlspecialchars($username) ?>"
                           autocomplete="username" class="<?= isset($acct_errors['name']) ? 'has-error' : '' ?>">
                    <?php if (isset($acct_errors['name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($acct_errors['name']) ?></div>
                    <?php else: ?>
                        <div class="field-hint">3-30 characters: letters, numbers, or underscores.</div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= htmlspecialchars($email) ?>"
                           autocomplete="email" class="<?= isset($acct_errors['email']) ? 'has-error' : '' ?>">
                    <?php if (isset($acct_errors['email'])): ?><div class="field-error"><?= htmlspecialchars($acct_errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>

        <h2 class="section-title">Change password</h2>
        <div class="form-card">
            <form id="password-form" method="POST" action="profile.php" novalidate>
                <input type="hidden" name="action" value="password">
                <?php if (!empty($pw_errors['general'])): ?>
                    <div class="alert alert-error" role="alert"><?= htmlspecialchars($pw_errors['general']) ?></div>
                <?php endif; ?>

                <div class="field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                           class="<?= isset($pw_errors['current']) ? 'has-error' : '' ?>">
                    <?php if (isset($pw_errors['current'])): ?><div class="field-error"><?= htmlspecialchars($pw_errors['current']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" autocomplete="new-password"
                           class="<?= isset($pw_errors['new']) ? 'has-error' : '' ?>">
                    <?php if (isset($pw_errors['new'])): ?>
                        <div class="field-error"><?= htmlspecialchars($pw_errors['new']) ?></div>
                    <?php else: ?>
                        <div class="field-hint">At least 8 characters.</div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password"
                           class="<?= isset($pw_errors['confirm']) ? 'has-error' : '' ?>">
                    <?php if (isset($pw_errors['confirm'])): ?><div class="field-error"><?= htmlspecialchars($pw_errors['confirm']) ?></div><?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="button" id="change-pw-btn" class="btn btn-primary">Change password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal Dialog -->
    <div id="confirm-modal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div class="modal-card" style="background: white; padding: 24px; border-radius: 8px; max-width: 400px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <h3>Confirm Password Change?</h3>
            <p style="margin: 12px 0 20px;">You will be logged out if confirmed.</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="modal-no" class="btn" style="background: #e2e8f0; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">No</button>
                <button type="button" id="modal-yes" class="btn btn-primary" style="background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Yes</button>
            </div>
        </div>
    </div>

    <script>
       // Intercepts password form submission to validate all fields client-side 
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('change-pw-btn');
            const modal = document.getElementById('confirm-modal');
            const yesBtn = document.getElementById('modal-yes');
            const noBtn = document.getElementById('modal-no');
            const form = document.getElementById('password-form');

            if (btn) {
                btn.addEventListener('click', function(e) {
                    const cur = document.getElementById('current_password').value;
                    const nw = document.getElementById('new_password').value;
                    const cf = document.getElementById('confirm_password').value;

                    const curField = document.getElementById('current_password');
                    const newField = document.getElementById('new_password');
                    const cfField = document.getElementById('confirm_password');

                    // Clear all previous dynamic error states and restore hints
                    [curField, newField, cfField].forEach(f => {
                        f.classList.remove('has-error');
                        const container = f.closest('.field');
                        container.querySelectorAll('.field-error-dynamic').forEach(el => el.remove());
                        const hint = container.querySelector('.field-hint');
                        if (hint) hint.style.display = '';
                    });

                    let hasError = false;

                    // Check current password
                    if (!cur) {
                        curField.classList.add('has-error');
                        const container = curField.closest('.field');
                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Please enter your current password.';
                        container.appendChild(errDiv);
                        hasError = true;
                    }

                    // Check new password presence, length, and distinctness
                    if (!nw) {
                        newField.classList.add('has-error');
                        const container = newField.closest('.field');
                        const hint = container.querySelector('.field-hint');
                        if (hint) hint.style.display = 'none';

                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Please enter a password.';
                        container.appendChild(errDiv);
                        hasError = true;
                    } else if (nw.length < 8) {
                        newField.classList.add('has-error');
                        const container = newField.closest('.field');
                        const hint = container.querySelector('.field-hint');
                        if (hint) hint.style.display = 'none';

                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Password must be at least 8 characters long.';
                        container.appendChild(errDiv);
                        hasError = true;
                    } else if (cur && cur === nw) {
                        newField.classList.add('has-error');
                        const container = newField.closest('.field');
                        const hint = container.querySelector('.field-hint');
                        if (hint) hint.style.display = 'none';

                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Your new password must be different from your current password.';
                        container.appendChild(errDiv);
                        hasError = true;
                    }

                    // Check confirm password
                    if (!cf) {
                        cfField.classList.add('has-error');
                        const container = cfField.closest('.field');

                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Please confirm your new password.';
                        container.appendChild(errDiv);
                        hasError = true;
                    } else if (nw !== cf) {
                        cfField.classList.add('has-error');
                        const container = cfField.closest('.field');

                        const errDiv = document.createElement('div');
                        errDiv.className = 'field-error field-error-dynamic';
                        errDiv.textContent = 'Passwords do not match. Please re-enter.';
                        container.appendChild(errDiv);
                        hasError = true;
                    }

                    // Stop execution if any validation failed
                    if (hasError) {
                        return;
                    }

                    // If all validation checks pass, display the confirmation modal
                    modal.style.display = 'flex';
                });
            }

            if (yesBtn) {
                yesBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                    form.submit();
                });
            }

            if (noBtn) {
                noBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>