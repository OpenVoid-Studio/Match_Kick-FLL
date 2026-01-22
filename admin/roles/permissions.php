<?php
session_start();
require_once '../../config.php';
require_once '../../auth.php';
require_once '../../event_pages.php';

// Superuser-only access
if (empty($_SESSION["is_superuser"]) || $_SESSION["is_superuser"] != 1) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    exit;
}

$role_id = intval($_GET["role_id"] ?? 0);

// Fetch role info
$stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
$stmt->bind_param("i", $role_id);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();

if (!$role) {
    echo "<h1>Role not found</h1>";
    exit;
}

// ---------------------------------------------------------
// SAVE PERMISSIONS
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Clear existing permissions
    $stmt = $conn->prepare("DELETE FROM role_page_access WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();

    // Insert new permissions
    $stmt = $conn->prepare("
        INSERT INTO role_page_access (role_id, page_slug, page_permission)
        VALUES (?, ?, ?)
    ");

    foreach ($PAGES as $slug => $label) {
        if (isset($_POST["perm_$slug"])) {
            $perm = intval($_POST["perm_$slug"]); // 0 or 1
            $stmt->bind_param("isi", $role_id, $slug, $perm);
            $stmt->execute();
        }
    }

    header("Location: /admin/roles/permissions.php?role_id=$role_id&saved=1");
    exit;
}

// ---------------------------------------------------------
// LOAD EXISTING PERMISSIONS
// ---------------------------------------------------------
$existing = [];
$res = $conn->query("
    SELECT page_slug, page_permission
    FROM role_page_access
    WHERE role_id = $role_id
");

while ($row = $res->fetch_assoc()) {
    $existing[$row["page_slug"]] = intval($row["page_permission"]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Permissions</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include '../nav.php'; ?>

<div class="container py-4">

    <h3>Permissions for Role: <?= htmlspecialchars($role["role_name"]) ?></h3>

    <?php if (isset($_GET["saved"])): ?>
        <div class="alert alert-success mt-3">Permissions updated.</div>
    <?php endif; ?>

    <form method="POST" class="mt-4">

        <table class="table table-bordered bg-white">
            <thead>
                <tr>
                    <th>Page</th>
                    <th style="width: 120px;">No Access</th>
                    <th style="width: 120px;">View</th>
                    <th style="width: 120px;">Edit</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($PAGES as $slug => $label): 
                    $perm = $existing[$slug] ?? -1; // -1 = no access
                ?>
                <tr>
                    <td><?= htmlspecialchars($label) ?><br><small class="text-muted"><?= $slug ?></small></td>

                    <!-- No Access -->
                    <td class="text-center">
                        <input type="radio" name="perm_<?= $slug ?>" value="-1" class="form-check-input"
                            <?= $perm === -1 ? "checked" : "" ?>>
                    </td>

                    <!-- View -->
                    <td class="text-center">
                        <input type="radio" name="perm_<?= $slug ?>" value="0" class="form-check-input"
                            <?= $perm === 0 ? "checked" : "" ?>>
                    </td>

                    <!-- Edit -->
                    <td class="text-center">
                        <input type="radio" name="perm_<?= $slug ?>" value="1" class="form-check-input"
                            <?= $perm === 1 ? "checked" : "" ?>>
                    </td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

        <button class="btn btn-primary">Save Permissions</button>
        <a href="/admin/roles" class="btn btn-secondary">Back</a>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
