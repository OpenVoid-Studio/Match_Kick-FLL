<?php
if (!isset($_SESSION)) session_start();

require_once '../config.php';
require_once '../permissions.php';
require_once '../event_pages.php';

// Required: $event_code must be set by the page including this navbar
if (!isset($event_code)) {
    echo "<div class='alert alert-danger'>Navbar error: \$event_code not set.</div>";
    return;
}

$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">

        <a class="navbar-brand" href="/event/<?= $event_code ?>"><?= $event_code ?> Event Panel</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#eventNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="eventNav">

            <ul class="navbar-nav">

                <?php foreach ($PAGES as $slug => $label): ?>

                    <?php
                        // Superusers see everything
                        if ($is_superuser) {
                            $allowed = true;
                        } else {
                            // Check permission (0=view, 1=edit)
                            $perm = get_permission($slug, $event_id);
                            $allowed = ($perm !== false);
                        }
                    ?>

                    <?php if ($allowed): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $slug ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        </li>
                    <?php endif; ?>

                <?php endforeach; ?>

            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?= htmlspecialchars($username) ?>
                        <?= $is_superuser ? " (Superuser)" : "" ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/admin/dashboard">My Events</a></li>
                        <li><a class="dropdown-item" href="/admin/change-password">Change Password</a></li>
                        <li><a class="dropdown-item text-danger" href="/admin/logout.php">Logout</a></li>
                    </ul>
                </li>

            </ul>

        </div>
    </div>
</nav>
