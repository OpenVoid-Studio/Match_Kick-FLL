<?php
session_start();
include_once '../config.php';

// User must be logged in
if (!isset($_SESSION["user"])) {
    header("Location: /admin");
    exit;
}

$error = "";
$success = "";

// Fetch current user info
$stmt = $conn->prepare("
    SELECT username, password, must_change_password 
    FROM users 
    WHERE username = ?
    LIMIT 1
");
$stmt->bind_param("s", $_SESSION["user"]);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // Session username doesn't exist in DB anymore
    session_destroy();
    header("Location: /admin");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current = trim($_POST["current_password"]);
    $new = trim($_POST["new_password"]);
    $confirm = trim($_POST["confirm_password"]);

    // Validate current password
    if (!password_verify($current, $user["password"])) {
        $error = "Your current password is incorrect.";
    }
    // Validate new passwords match
    elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    }
    // Validate password length
    elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    }
    else {
        // Update password
        $hashed = password_hash($new, PASSWORD_DEFAULT);

        $update = $conn->prepare("
            UPDATE users 
            SET password = ?, must_change_password = 0 
            WHERE username = ?
        ");
        $update->bind_param("ss", $hashed, $user["username"]);
        $update->execute();

        // Clear forced-change flag in session
        unset($_SESSION["pw_change"]);

        $success = "Password updated successfully.";
        header("Refresh: 2; URL=/admin/dashboard");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
    <div class="card shadow p-4" style="width: 420px;">
        <h3 class="text-center mb-4">Change Password</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
</div>

</body>
</html>
