<?php
if (!isset($_SESSION)) session_start();

$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;
$username = $_SESSION["user"] ?? "Unknown";
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">

        <a class="navbar-brand" href="/admin/dashboard">Match Kick Panel</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">

            <ul class="navbar-nav">

                <!-- Always visible -->
                <li class="nav-item">
                    <a class="nav-link" href="/admin/dashboard">Events Dashboard</a>
                </li>

                <!-- Superuser-only admin pages -->
                <?php if ($is_superuser): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users">User Manager</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/admin/roles">Role Manager</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/admin/event-requests">Event Requests</a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- Right side -->
            <ul class="navbar-nav ms-auto">

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?= htmlspecialchars($username) ?>
                        <?= $is_superuser ? " (Superuser)" : "" ?>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/admin/change-password">Change Password</a></li>
                        <li><a class="dropdown-item text-danger" href="/admin/logout.php">Logout</a></li>
                    </ul>
                </li>

            </ul>

        </div>
    </div>
</nav>
