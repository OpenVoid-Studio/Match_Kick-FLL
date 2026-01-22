<?php
include_once '../config.php';

if (isset($_SESSION["user"])) {
    header("Location: /admin/dashboard");
    exit;
}

$error = "";

/* -------------------------------------------------------
   AUTO‑CREATE DEFAULT ADMIN IF NO USERS EXIST
------------------------------------------------------- */
$check = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $check->fetch_assoc();

if ($row["total"] == 0) {
    $defaultUser = "admin";
    $defaultPass = "changeme"; // user must change this on first login
    $hashed = password_hash($defaultPass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (username, password, must_change_password, is_superuser)
        VALUES (?, ?, 1, 1)
    ");
    $stmt->bind_param("ss", $defaultUser, $hashed);
    $stmt->execute();
}

/* -------------------------------------------------------
   LOGIN HANDLING
------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("
        SELECT username, password, must_change_password, is_superuser 
        FROM users 
        WHERE username = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            // Store session data
            $_SESSION["user"] = $user["username"];
            $_SESSION["is_superuser"] = (int)$user["is_superuser"];

            // Force password change if required
            if ($user["must_change_password"] == 1) {
                $_SESSION["pw_change"] = true;
                header("Location: /admin/change-password");
                exit;
            }

            // Update last login timestamp
            $update = $conn->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE username = ?
            ");
            $update->bind_param("s", $user["username"]);
            $update->execute();

            header("Location: /admin/dashboard");
            exit;
        }
    }

    // Generic error message (same for all failures)
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Kick Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
    <div class="card shadow p-4" style="width: 380px;">
        <h3 class="text-center mb-4">Match Kick Login</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>

</body>
</html>
