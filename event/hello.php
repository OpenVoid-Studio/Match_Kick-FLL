<?php
session_start();
require_once '../config.php';
require_once '../auth.php';
require_once '../permissions.php';

// ---------------------------------------------------------
// Validate event_code
// ---------------------------------------------------------
if (!isset($_GET["event_code"])) {
    echo "<h1>Invalid Event</h1>";
    exit;
}



$event_code = trim($_GET["event_code"]);

// Fetch event by code
$stmt = $conn->prepare("SELECT * FROM events WHERE event_code = ?");
$stmt->bind_param("s", $event_code);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo "<h1>Event Not Found</h1>";
    exit;
}

$event_id = $event["event_id"];

// ---------------------------------------------------------
// Fetch user roles for this event
// ---------------------------------------------------------
$username = $_SESSION["user"];
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

$roles = [];

if ($is_superuser) {
    $roles[] = "Superuser (full access)";
} else {
    $stmt = $conn->prepare("
        SELECT r.role_name
        FROM user_event_roles uer
        JOIN roles r ON r.role_id = uer.role_id
        WHERE uer.user_id = ? AND uer.event_id = ?
    ");
    $stmt->bind_param("si", $username, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $roles[] = $row["role_name"];
    }

    $stmt->close();

    
    if (!$is_superuser && empty($roles)) {
        echo "<h1>Access Denied</h1><p>You do not have permission to access this event.</p>";
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Event Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <h2 class="mb-3">Welcome to <?= htmlspecialchars($event["event_title"]) ?>'s Match Kick event panel.</h2>
    <p class="text-muted">
        <?= $event["event_description"] ? htmlspecialchars($event["event_description"]) : "<em>No description provided.</em>" ?>
    </p>


    <div class="card mb-4">
        <div class="card-body">
            <h5>Your Role at This Event</h5>

            <?php if (empty($roles)): ?>
                <p class="text-danger mb-0">You do not have any assigned roles for this event.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($roles as $r): ?>
                        <li><?= htmlspecialchars($r) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            <h5>What You Can Do Here</h5>
            <p>This event panel gives you access to tools and information based on your assigned roles.</p>

            <ul>
                <li>Use the navigation bar above to access event pages.</li>
                <li>Pages you don’t have permission for will not appear.</li>
                <?php if ($is_superuser): ?>
                    <li>As a superuser, you have full access to all event management features.</li>
                <?php endif; ?>
                <li>If you believe you should have additional permissions, please contact your event manager.</li>
                <li>For support and bug reports visit <a href="https://github.com/OpenVoid-Studio/Match_Kick-FLL">Match Kick's FLL Repo on GitHub</a></li>
            </ul>

            <p class="text-muted mb-0">
                Event Start Date: <?= date("F j, Y", strtotime($event["event_start"])) ?>
            </p>

        </div>
    </div>

    <p>Match Kick is a third party FLL event management system designed to level up any event.</p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
