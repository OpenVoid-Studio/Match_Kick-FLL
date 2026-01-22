<?php
session_start();
require_once '../config.php';
require_once '../auth.php'; // ensures user is logged in

// ---------------------------------------------------------
// Fetch events based on user type
// ---------------------------------------------------------

$username = $_SESSION["user"];
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($is_superuser) {
    // Superusers see ALL events
    $stmt = $conn->prepare("
        SELECT event_id, event_code, event_title, event_start, event_status
        FROM events
        ORDER BY event_start DESC
    ");
    $stmt->execute();
    $events = $stmt->get_result();

} else {
    // Normal users see only events where they have a role
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_code, e.event_title, e.event_start, e.event_status
        FROM events e
        JOIN user_event_roles uer ON uer.event_id = e.event_id
        WHERE uer.user_id = ?
        GROUP BY e.event_id
        ORDER BY e.event_start DESC
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $events = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Events Dashboard</h3>

        <?php if ($is_superuser): ?>
            <a href="/admin/create-event" class="btn btn-primary">Create Event</a>
        <?php else: ?>
            <a href="/admin/request-event" class="btn btn-secondary">Request New Event</a>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th style="width: 120px;">Event Code</th>
                <th>Title</th>
                <th style="width: 180px;">Start Date</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 150px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($events->num_rows === 0): ?>
                <tr>
                    <td colspan="5" class="text-center py-3">
                        <em>No events available.</em>
                    </td>
                </tr>
            <?php else: ?>
                <?php while ($row = $events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['event_code']) ?></td>
                        <td><?= htmlspecialchars($row['event_title']) ?></td>
                        <td><?= date("Y-m-d H:i", strtotime($row['event_start'])) ?></td>
                        <td>
                            <?php
                                echo match($row['event_status']) {
                                    0 => '<span class="badge bg-secondary">Draft</span>',
                                    1 => '<span class="badge bg-success">Active</span>',
                                    2 => '<span class="badge bg-danger">Closed</span>',
                                    default => '<span class="badge bg-dark">Unknown</span>',
                                };
                            ?>
                        </td>
                        <td>
                            <a href="/event/<?= $row['event_code'] ?>" class="btn btn-sm btn-primary">
                                Open Event
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
