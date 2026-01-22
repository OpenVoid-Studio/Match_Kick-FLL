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
$perm = get_permission("teams", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle Add/Edit Team
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $team_id = intval($_POST["team_id"]);
    $team_num = intval($_POST["team_num"]);
    $team_name = trim($_POST["team_name"]);

    if ($team_id === 0) {
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO teams (event_id, team_num, team_name)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $event_id, $team_num, $team_name);
    } else {
        // Update
        $stmt = $conn->prepare("
            UPDATE teams
            SET team_num = ?, team_name = ?
            WHERE team_id = ? AND event_id = ?
        ");
        $stmt->bind_param("isii", $team_num, $team_name, $team_id, $event_id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/teams");
    exit;
}

// ---------------------------------------------------------
// Handle Delete
// ---------------------------------------------------------
if (isset($_GET["delete"]) && $can_edit) {
    $del_id = intval($_GET["delete"]);

    $stmt = $conn->prepare("DELETE FROM teams WHERE team_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $del_id, $event_id);
    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/teams");
    exit;
}

// ---------------------------------------------------------
// Fetch Teams
// ---------------------------------------------------------
$stmt = $conn->prepare("
    SELECT team_id, team_num, team_name
    FROM teams
    WHERE event_id = ?
    ORDER BY team_num ASC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$teams = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Teams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Teams</h3>

        <?php if ($can_edit): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">Add Team</button>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th style="width: 120px;">Team #</th>
                <th>Team Name</th>
                <?php if ($can_edit): ?>
                    <th style="width: 180px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $teams->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['team_num'] ?></td>
                    <td><?= htmlspecialchars($row['team_name']) ?></td>

                    <?php if ($can_edit): ?>
                        <td>
                            <button 
                                class="btn btn-sm btn-warning"
                                onclick="openEditModal(
                                    <?= $row['team_id'] ?>,
                                    <?= $row['team_num'] ?>,
                                    '<?= htmlspecialchars($row['team_name'], ENT_QUOTES) ?>'
                                )"
                            >
                                Edit
                            </button>

                            <a href="/event/teams.php?event_code=<?= $event_code ?>&delete=<?= $row['team_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this team?')">
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
<div class="modal fade" id="teamModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">

      <input type="hidden" name="team_id" id="team_id">

      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Team</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Team Number</label>
            <input type="number" name="team_num" id="team_num" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Team Name</label>
            <input type="text" name="team_name" id="team_name" class="form-control">
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
let modal = new bootstrap.Modal(document.getElementById('teamModal'));

function openEditModal(id, num, name) {
    document.getElementById('modalTitle').innerText = "Edit Team";
    document.getElementById('team_id').value = id;
    document.getElementById('team_num').value = num;
    document.getElementById('team_name').value = name;
    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Add Team";
    document.getElementById('team_id').value = 0;
    document.getElementById('team_num').value = "";
    document.getElementById('team_name').value = "";
    modal.show();
}
</script>

</body>
</html>
