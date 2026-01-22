<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Superuser-only access
if (empty($_SESSION["is_superuser"]) || $_SESSION["is_superuser"] != 1) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    exit;
}

// ---------------------------------------------------------
// CREATE / UPDATE USER
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $is_superuser = isset($_POST["is_superuser"]) ? 1 : 0;

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (username, password, is_superuser, must_change_password)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                password = VALUES(password),
                is_superuser = VALUES(is_superuser),
                must_change_password = 1
        ");
        $stmt->bind_param("ssi", $username, $hashed, $is_superuser);

    } else {
        // Update without changing password
        $stmt = $conn->prepare("
            UPDATE users 
            SET is_superuser = ?
            WHERE username = ?
        ");
        $stmt->bind_param("is", $is_superuser, $username);
    }

    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------------
// DELETE USER
// ---------------------------------------------------------
if (isset($_GET["delete"])) {
    $delUser = $_GET["delete"];

    if ($delUser !== $_SESSION["user"]) { // prevent self-delete
        $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
        $stmt->bind_param("s", $delUser);
        $stmt->execute();
    }

    header("Location: /admin/users");
    exit;
}

// ---------------------------------------------------------
// FETCH USERS
// ---------------------------------------------------------
$result = $conn->query("
    SELECT username, is_superuser, last_login 
    FROM users 
    ORDER BY username ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Manager</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>User Manager</h3>
        <button class="btn btn-primary" onclick="openCreateModal()">Add User</button>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Username</th>
                <th>Last Login</th>
                <th>Superuser</th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?php if ($row['last_login']) { ?><?= htmlspecialchars($row['last_login']) ?> UTC <?php } else { ?><span class="text-danger">Never</span><?php } ?></td>
                    <td><?= $row['is_superuser'] ? "Yes" : "No" ?></td>
                    <td>
                        <button 
                            class="btn btn-sm btn-warning"
                            onclick="openEditModal('<?= $row['username'] ?>', <?= $row['is_superuser'] ?>)"
                        >
                            Edit
                        </button>

                        <?php if ($row['username'] !== $_SESSION["user"]): ?>
                            <a href="/admin/users?delete=<?= $row['username'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this user?')">
                               Delete
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" id="username" required>
        </div>

        <div class="mb-3">
            <label class="form-label">New Password (leave blank to keep current)</label>
            <input type="text" class="form-control" name="password" id="password">
            <span class="form-text" id="passwordHelp">
                If you set a new password, the user will be required to change it on next login.
            </span>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_superuser" id="is_superuser">
            <label class="form-check-label">Superuser</label>
        </div>

      </div>

      <div class="modal-footer">
        <input type="button" value="Cancel" class="btn btn-secondary" data-bs-dismiss="modal">
        <button class="btn btn-primary" type="submit">Save</button>
      </div>

    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let modal = new bootstrap.Modal(document.getElementById('userModal'));

function openEditModal(username, superuser) {
    document.getElementById('modalTitle').innerText = "Edit User";
    document.getElementById('username').value = username;
    document.getElementById('username').readOnly = true;
    document.getElementById('password').value = "";
    document.getElementById('passwordHelp').innerText = "If you set a new password, the user will be required to change it on next login.";
    document.getElementById('is_superuser').checked = superuser == 1;
    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Create User";
    document.getElementById('username').value = "";
    document.getElementById('username').readOnly = false;
    document.getElementById('password').value = "M@tchK1ckDefault";
    document.getElementById('passwordHelp').innerText = "A default password is set. The user will be required to change it on next login.";
    document.getElementById('is_superuser').checked = false;
    modal.show();
}
</script>

</body>
</html>
