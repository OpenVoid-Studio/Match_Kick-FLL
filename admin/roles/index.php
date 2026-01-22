<?php
session_start();
require_once '../../config.php';
require_once '../../auth.php';

// Superuser-only access
if (empty($_SESSION["is_superuser"]) || $_SESSION["is_superuser"] != 1) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    exit;
}

// ---------------------------------------------------------
// CREATE / UPDATE ROLE
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role_id = intval($_POST["role_id"]);
    $role_name = trim($_POST["role_name"]);
    $role_description = trim($_POST["role_description"]);

    if ($role_id === 0) {
        // Create new role
        $stmt = $conn->prepare("
            INSERT INTO roles (role_name, role_description)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ss", $role_name, $role_description);
    } else {
        // Update existing role
        $stmt = $conn->prepare("
            UPDATE roles
            SET role_name = ?, role_description = ?
            WHERE role_id = ?
        ");
        $stmt->bind_param("ssi", $role_name, $role_description, $role_id);
    }

    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------------
// DELETE ROLE
// ---------------------------------------------------------
if (isset($_GET["delete"])) {
    $del_id = intval($_GET["delete"]);

    // Delete role + its page permissions
    $conn->query("DELETE FROM role_page_access WHERE role_id = $del_id");
    $conn->query("DELETE FROM roles WHERE role_id = $del_id");

    header("Location: /admin/roles");
    exit;
}

// ---------------------------------------------------------
// FETCH ROLES
// ---------------------------------------------------------
$result = $conn->query("
    SELECT role_id, role_name, role_description
    FROM roles
    ORDER BY role_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Manager</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include '../nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Role Manager</h3>
        <button class="btn btn-primary" onclick="openCreateModal()">Add Role</button>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Role Name</th>
                <th>Description</th>
                <th style="width: 220px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['role_name']) ?></td>
                    <td><?= htmlspecialchars($row['role_description']) ?></td>
                    <td>
                        <button 
                            class="btn btn-sm btn-warning"
                            onclick="openEditModal(
                                <?= $row['role_id'] ?>,
                                '<?= htmlspecialchars($row['role_name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['role_description'], ENT_QUOTES) ?>'
                            )"
                        >
                            Edit
                        </button>

                        <a href="/admin/roles/permissions.php?role_id=<?= $row['role_id'] ?>"
                           class="btn btn-sm btn-primary">
                           Permissions
                        </a>

                        <a href="/admin/roles?delete=<?= $row['role_id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this role?')">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" name="role_id" id="role_id">

        <div class="mb-3">
            <label class="form-label">Role Name</label>
            <input type="text" class="form-control" name="role_name" id="role_name" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="role_description" id="role_description"></textarea>
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
let modal = new bootstrap.Modal(document.getElementById('roleModal'));

function openEditModal(id, name, desc) {
    document.getElementById('modalTitle').innerText = "Edit Role";
    document.getElementById('role_id').value = id;
    document.getElementById('role_name').value = name;
    document.getElementById('role_description').value = desc;
    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Create Role";
    document.getElementById('role_id').value = 0;
    document.getElementById('role_name').value = "";
    document.getElementById('role_description').value = "";
    modal.show();
}
</script>

</body>
</html>
