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

// Fetch event
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
// Permission check
// ---------------------------------------------------------
$perm = get_permission("event/users", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle Add User to Event
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $username = trim($_POST["username"]);
    $role_id = intval($_POST["role_id"]);

    // Check if user exists
    $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exists) {
        $error = "User does not exist.";
    } else {
        // Add role
        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_event_roles (user_id, event_id, role_id)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sii", $username, $event_id, $role_id);
        $stmt->execute();
        $stmt->close();

        header("Location: /event/users.php?event_code=$event_code");
        exit;
    }
}

// ---------------------------------------------------------
// Handle Remove Role
// ---------------------------------------------------------
if (isset($_GET["remove"]) && $can_edit) {
    $remove_id = intval($_GET["remove"]);
    $user_id = $_GET["user"];

    $stmt = $conn->prepare("
        DELETE FROM user_event_roles 
        WHERE user_id = ? AND event_id = ? AND role_id = ?
    ");
    $stmt->bind_param("sii", $user_id, $event_id, $remove_id);
    $stmt->execute();
    $stmt->close();

    header("Location: /event/users.php?event_code=$event_code");
    exit;
}

// ---------------------------------------------------------
// Fetch all roles
// ---------------------------------------------------------
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");

// ---------------------------------------------------------
// Fetch event users + roles
// ---------------------------------------------------------
$stmt = $conn->prepare("
    SELECT u.username, r.role_id, r.role_name
    FROM user_event_roles uer
    JOIN users u ON u.username = uer.user_id
    JOIN roles r ON r.role_id = uer.role_id
    WHERE uer.event_id = ?
    ORDER BY u.username, r.role_name
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Group roles by user
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[$row["username"]][] = [
        "role_id" => $row["role_id"],
        "role_name" => $row["role_name"]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Event Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Event User Management</h3>

        <?php if ($can_edit): ?>
            <button class="btn btn-primary" onclick="openAddModal()">Add User</button>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>User</th>
                <th>Roles</th>
                <?php if ($can_edit): ?><th style="width: 150px;">Actions</th><?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($users as $username => $roleList): ?>
                <tr>
                    <td><?= htmlspecialchars($username) ?></td>

                    <td>
                        <?php foreach ($roleList as $r): ?>
                            <span class="badge bg-secondary me-1">
                                <?= htmlspecialchars($r["role_name"]) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>

                    <?php if ($can_edit): ?>
                        <td>
                            <?php foreach ($roleList as $r): ?>
                                <a href="/event/users.php?event_code=<?= $event_code ?>&user=<?= urlencode($username) ?>&remove=<?= $r['role_id'] ?>"
                                   class="btn btn-sm btn-danger mb-1"
                                   onclick="return confirm('Remove this role?')">
                                   Remove <?= htmlspecialchars($r["role_name"]) ?>
                                </a>
                            <?php endforeach; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">

      <div class="modal-header">
        <h5 class="modal-title">Add User to Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
            <small class="text-muted">Must be an existing account</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-select" required>
                <?php while ($r = $roles->fetch_assoc()): ?>
                    <option value="<?= $r['role_id'] ?>">
                        <?= htmlspecialchars($r['role_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Add User</button>
      </div>

    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let modal = new bootstrap.Modal(document.getElementById('addUserModal'));

function openAddModal() {
    modal.show();
}
</script>

</body>
</html>
