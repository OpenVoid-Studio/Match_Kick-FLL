<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

if (empty($_SESSION["is_superuser"])) {
    echo "<h1>403 Forbidden</h1>";
    exit;
}

// Handle deny
if (isset($_GET["deny"])) {
    $id = intval($_GET["deny"]);
    $conn->query("UPDATE event_requests SET status='denied' WHERE request_id=$id");
    header("Location: /admin/event-requests.php");
    exit;
}

$requests = $conn->query("
    SELECT * FROM event_requests
    ORDER BY submitted_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Event Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <h3 class="mb-4">Event Requests</h3>

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Requested By</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Title</th>
                <th>Description</th>
                <th>Preferred Date</th>
                <th>Status</th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r["requested_by"]) ?></td>
                    <td><?= htmlspecialchars($r["email"]) ?></td>
                    <td><?= htmlspecialchars($r["phone"]) ?></td>
                    <td><?= htmlspecialchars($r["event_title"]) ?></td>
                    <td><?= nl2br(htmlspecialchars($r["event_description"])) ?></td>
                    <td><?= $r["preferred_date"] ?: "<em>None</em>" ?></td>
                    <td>
                        <?php
                            echo match($r["status"]) {
                                "pending" => '<span class="badge bg-warning">Pending</span>',
                                "approved" => '<span class="badge bg-success">Approved</span>',
                                "denied" => '<span class="badge bg-danger">Denied</span>',
                            };
                        ?>
                    </td>
                    <td>
                        <?php if ($r["status"] === "pending"): ?>
                            <a href="/admin/create-event?prefill=<?= $r["request_id"] ?>"
                               class="btn btn-sm btn-primary">Approve</a>

                            <a href="/admin/event-requests.php?deny=<?= $r["request_id"] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Deny this request?')">Deny</a>
                        <?php else: ?>
                            <em>No actions</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
