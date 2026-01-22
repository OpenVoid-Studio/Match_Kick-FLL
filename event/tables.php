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
$perm = get_permission("event/tables", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle Add/Edit Table
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $table_id = intval($_POST["table_id"]);
    $table_name = trim($_POST["table_name"]);
    $team1_color = trim($_POST["team1_color"]);
    $team2_color = trim($_POST["team2_color"]);
    $title_color = trim($_POST["title_color"]);
    $timer_color = trim($_POST["timer_color"]);

    if ($table_id === 0) {
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO match_tables (event_id, table_name, team1_color, team2_color, title_color, timer_color)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $event_id, $table_name, $team1_color, $team2_color, $title_color, $timer_color);
    } else {
        // Update
        $stmt = $conn->prepare("
            UPDATE match_tables
            SET table_name = ?, team1_color = ?, team2_color = ?, title_color = ?, timer_color = ?
            WHERE table_id = ? AND event_id = ?
        ");
        $stmt->bind_param("sssssi", $table_name, $team1_color, $team2_color, $title_color, $timer_color, $table_id, $event_id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/tables");
    exit;
}

// ---------------------------------------------------------
// Handle Delete
// ---------------------------------------------------------
if (isset($_GET["delete"]) && $can_edit) {
    $del_id = intval($_GET["delete"]);

    $stmt = $conn->prepare("DELETE FROM match_tables WHERE table_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $del_id, $event_id);
    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/tables");
    exit;
}

// ---------------------------------------------------------
// Fetch Tables
// ---------------------------------------------------------
$stmt = $conn->prepare("
    SELECT table_id, table_name, team1_color, team2_color, title_color, timer_color
    FROM match_tables
    WHERE event_id = ?
    ORDER BY table_name ASC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$tables = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Tables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Match Tables</h3>

        <?php if ($can_edit): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">Add Table</button>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Table Name</th>
                <th>Team 1 Color</th>
                <th>Team 2 Color</th>
                <th>Title Color</th>
                <th>Timer Color</th>
                <?php if ($can_edit): ?>
                    <th style="width: 180px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $tables->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['table_name']) ?></td>
                    <td><span style="background: <?= $row['team1_color'] ?>; padding: 4px 12px; border-radius: 4px;"></span> <?= $row['team1_color'] ?></td>
                    <td><span style="background: <?= $row['team2_color'] ?>; padding: 4px 12px; border-radius: 4px;"></span> <?= $row['team2_color'] ?></td>
                    <td><span style="background: <?= $row['title_color'] ?>; padding: 4px 12px; border-radius: 4px;"></span> <?= $row['title_color'] ?></td>
                    <td><span style="background: <?= $row['timer_color'] ?>; padding: 4px 12px; border-radius: 4px;"></span> <?= $row['timer_color'] ?></td>

                    <?php if ($can_edit): ?>
                        <td>
                            <button 
                                class="btn btn-sm btn-warning"
                                onclick="openEditModal(
                                    <?= $row['table_id'] ?>,
                                    '<?= htmlspecialchars($row['table_name'], ENT_QUOTES) ?>',
                                    '<?= $row['team1_color'] ?>',
                                    '<?= $row['team2_color'] ?>',
                                    '<?= $row['title_color'] ?>',
                                    '<?= $row['timer_color'] ?>'
                                )"
                            >
                                Edit
                            </button>

                            <a href="/event/tables.php?event_code=<?= $event_code ?>&delete=<?= $row['table_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this table?')">
                               Delete
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="tableModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">

      <input type="hidden" name="table_id" id="table_id">

      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Table</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Table Name</label>
            <input type="text" name="table_name" id="table_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Team 1 Color</label>
            <input type="color" name="team1_color" id="team1_color" class="form-control form-control-color" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Team 2 Color</label>
            <input type="color" name="team2_color" id="team2_color" class="form-control form-control-color" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Title Color</label>
            <input type="color" name="title_color" id="title_color" class="form-control form-control-color" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Timer Color</label>
            <input type="color" name="timer_color" id="timer_color" class="form-control form-control-color" required>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>

    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let modal = new bootstrap.Modal(document.getElementById('tableModal'));

function openEditModal(id, name, c1, c2, c3, c4) {
    document.getElementById('modalTitle').innerText = "Edit Table";
    document.getElementById('table_id').value = id;
    document.getElementById('table_name').value = name;
    document.getElementById('team1_color').value = c1;
    document.getElementById('team2_color').value = c2;
    document.getElementById('title_color').value = c3;
    document.getElementById('timer_color').value = c4;
    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Add Table";
    document.getElementById('table_id').value = 0;
    document.getElementById('table_name').value = "";
    document.getElementById('team1_color').value = "#000000";
    document.getElementById('team2_color').value = "#000000";
    document.getElementById('title_color').value = "#000000";
    document.getElementById('timer_color').value = "#000000";
    modal.show();
}
</script>

</body>
</html>
