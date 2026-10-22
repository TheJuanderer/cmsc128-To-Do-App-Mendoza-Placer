<?php
// Expects $user (from require_login()).
// Shows who is logged in and a dropdown for log out.
$initial = strtoupper(mb_substr($user['user_name'], 0, 1));
?>
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">To-Do List</a>

        <div class="user-menu" id="user-menu">
            <button type="button" class="user-menu-btn" id="user-menu-btn"
                    aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-panel">
                <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
                <span class="user-menu-name"><?= htmlspecialchars($user['user_name']) ?></span>
                <span class="caret" aria-hidden="true">&#9662;</span>
            </button>

            <div class="user-menu-panel" id="user-menu-panel" hidden>
                <div class="user-menu-who">
                    Signed in as
                    <strong><?= htmlspecialchars($user['user_name']) ?></strong>
                    <span><?= htmlspecialchars($user['user_email']) ?></span>
                </div>
                <!-- Logging out changes state, so it is a POST form, not a plain link -->
                <form method="POST" action="logout.php">
                    <button type="submit" class="user-menu-item user-menu-logout">Log out</button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    (function () {
        const btn   = document.getElementById('user-menu-btn');
        const panel = document.getElementById('user-menu-panel');
        const menu  = document.getElementById('user-menu');

        function setOpen(open) {
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        btn.addEventListener('click', () => setOpen(panel.hidden));                 // click the button: toggle
        document.addEventListener('click', (e) => { if (!menu.contains(e.target)) setOpen(false); }); // click elsewhere: close
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } }); // Esc: close
    })();
</script>
