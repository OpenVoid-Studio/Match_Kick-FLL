<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Superuser-only access
if (empty($_SESSION["is_superuser"]) || $_SESSION["is_superuser"] != 1) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    exit;
}

$error = "";
$success = "";

// ---------------------------------------------------------
// PREFILL FROM EVENT REQUEST
// ---------------------------------------------------------
$prefill_title = "";
$prefill_desc = "";
$prefill_date = "";

if (isset($_GET["prefill"])) {
    $req_id = intval($_GET["prefill"]);

    $stmt = $conn->prepare("
        SELECT event_title, event_description, preferred_date, requested_by
        FROM event_requests
        WHERE request_id = ?
    ");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        $prefill_title = $result["event_title"];
        $prefill_desc = $result["event_description"];
        $prefill_date = $result["preferred_date"];
        $requested_by = $result["requested_by"];
    }
}

// ---------------------------------------------------------
// HANDLE FORM SUBMISSION
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_code = trim($_POST["event_code"]);
    $event_title = trim($_POST["event_title"]);
    $event_description = trim($_POST["event_description"]);
    $event_start = trim($_POST["event_start"]);
    $event_status = intval($_POST["event_status"]);
    $request_id = intval($_POST["request_id"] ?? 0);

    if ($event_code === "" || $event_title === "" || $event_start === "") {
        $error = "Event Code, Title, and Start Date are required.";
    } else {

        // Insert event
        $stmt = $conn->prepare("
            INSERT INTO events (event_code, event_title, event_description, event_start, event_status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssi", $event_code, $event_title, $event_description, $event_start, $event_status);

        if ($stmt->execute()) {

            // Mark request as approved if applicable
            if ($request_id > 0) {
                $conn->query("
                    UPDATE event_requests 
                    SET status='approved' 
                    WHERE request_id = $request_id
                ");

                // insert user into user_event_roles
                if (isset($requested_by)) {
                    $conn->query("
                        INSERT INTO user_event_roles (username, event_code, role_id)
                        VALUES ('$requested_by', '$event_code', 0)
                    ");
                }
            }

            $success = "Event created successfully!";
            header("Refresh: 2; URL=/admin/dashboard");
        } else {
            $error = "Error creating event: " . $conn->error;
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4" style="max-width: 700px;">

    <h3 class="mb-4">Create New Event</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">

        <!-- Hidden field to track request ID -->
        <input type="hidden" name="request_id" value="<?= $_GET["prefill"] ?? 0 ?>">

        <div class="mb-3">
            <label class="form-label">Event Code</label>
            <input type="text" name="event_code" class="form-control" required>
            <small class="text-muted">Short code, e.g. "FLL2025A"</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Event Title</label>
            <input type="text" name="event_title" class="form-control" 
                   value="<?= htmlspecialchars($prefill_title) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Event Description</label>
            <textarea name="event_description" class="form-control" rows="3"><?= htmlspecialchars($prefill_desc) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Start Date & Time</label>
            <input type="date" name="event_start" class="form-control" 
                   value="<?= htmlspecialchars($prefill_date) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Event Status</label>
            <select name="event_status" class="form-select">
                <option value="0">Draft</option>
                <option value="1">Active</option>
                <option value="2">Closed</option>
            </select>
        </div>

        <div class="d-flex justify-content-between">
            <a href="/admin/dashboard" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Create Event</button>
        </div>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
