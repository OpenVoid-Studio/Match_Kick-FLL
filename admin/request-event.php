<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);

    $title = trim($_POST["event_title"]);
    $desc = trim($_POST["event_description"]);
    $date = trim($_POST["preferred_date"]);
    $user = $_SESSION["user"];

    if ($title === "") {
        $error = "Event title is required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO event_requests 
                (requested_by, email, phone, event_title, event_description, preferred_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("ssssss", 
            $user, 
            $email, 
            $phone, 
            $title, 
            $desc, 
            $date
        );

        $stmt->execute();
        $stmt->close();

        $success = "Your event request has been submitted!";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Request Event</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4" style="max-width: 700px;">

    <h3 class="mb-4">Request a New Event</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">

        <p>Fill out the form below to request the creation of a new event. An administrator will review your request.</p>
        
        <h2 class="text-muted">Personal Details</h2>

        <div class="mb-3">
            <label class="form-label">Your Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Your Phone Number</label>
            <input type="tel" name="phone" class="form-control" required>
        </div>

        <h2 class="text-muted">Event Details</h2>
        
        <div class="mb-3">
            <label class="form-label">Event Title</label>
            <input type="text" name="event_title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Event Description</label>
            <textarea name="event_description" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Event Date (this can be changed later)</label>
            <input type="date" name="preferred_date" class="form-control" required>
        </div>

        <button class="btn btn-primary">Submit Request</button>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
