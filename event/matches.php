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
$perm = get_permission("event/matches", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle Add/Edit Match
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $match_id = intval($_POST["match_id"]);
    $match_type = intval($_POST["match_type"]);
    $team1_id = intval($_POST["team1_id"]);
    $team2_id = intval($_POST["team2_id"]);
    $table_id = intval($_POST["table_id"]);
    $match_order = intval($_POST["match_order"]);
    $status = intval($_POST["status"]);

    if ($match_id === 0) {
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO matches (event_id, match_type, team1_id, team2_id, table_id, match_order, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiiiii", $event_id, $match_type, $team1_id, $team2_id, $table_id, $match_order, $status);
    } else {
        // Update
        $stmt = $conn->prepare("
            UPDATE matches
            SET match_type = ?, team1_id = ?, team2_id = ?, table_id = ?, match_order = ?, status = ?
            WHERE match_id = ? AND event_id = ?
        ");
        $stmt->bind_param("iiiiiiii", $match_type, $team1_id, $team2_id, $table_id, $match_order, $status, $match_id, $event_id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/matches");
    exit;
}

// ---------------------------------------------------------
// Handle Delete
// ---------------------------------------------------------
if (isset($_GET["delete"]) && $can_edit) {
    $del_id = intval($_GET["delete"]);

    $stmt = $conn->prepare("DELETE FROM matches WHERE match_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $del_id, $event_id);
    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/matches");
    exit;
}

// ---------------------------------------------------------
// Fetch Teams
// ---------------------------------------------------------
$teams = $conn->query("
    SELECT team_id, team_num, team_name
    FROM teams
    WHERE event_id = $event_id
    ORDER BY team_num ASC
");

// ---------------------------------------------------------
// Fetch Tables
// ---------------------------------------------------------
$tables = $conn->query("
    SELECT table_id, table_name
    FROM match_tables
    WHERE event_id = $event_id
    ORDER BY table_name ASC
");

// ---------------------------------------------------------
// Fetch Matches (practice + official)
// ---------------------------------------------------------
$practiceMatches = $conn->query("
    SELECT m.*, 
           t.table_name,
           t1.team_num AS team1, t1.team_name AS team1_name,
           t2.team_num AS team2, t2.team_name AS team2_name
    FROM matches m
    JOIN match_tables t ON t.table_id = m.table_id
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.event_id = $event_id AND m.match_type = 0
    ORDER BY m.match_order ASC
");

$officialMatches = $conn->query("
    SELECT m.*, 
           t.table_name,
           t1.team_num AS team1, t1.team_name AS team1_name,
           t2.team_num AS team2, t2.team_name AS team2_name
    FROM matches m
    JOIN match_tables t ON t.table_id = m.table_id
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.event_id = $event_id AND m.match_type = 1
    ORDER BY m.match_order ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Matches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Matches</h3>

        <?php if ($can_edit): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">Add Match</button>
        <?php endif; ?>
    </div>

    <!-- PRACTICE MATCHES -->
    <h4>Practice Matches</h4>
    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Order</th>
                <th>Table</th>
                <th>Team 1</th>
                <th>Team 2</th>
                <th>Status</th>
                <?php if ($can_edit): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $practiceMatches->fetch_assoc()): ?>
                <?php
                    $status_english = match($row['status']) {
                        "0" => "Upcoming",
                        "1" => "Playing",
                        "2" => "Completed",
                        "3" => "Scores Submitted",
                        default => "Unknown"
                    };
                ?>
                <tr>
                    <td><?= $row['match_order'] ?></td>
                    <td><?= htmlspecialchars($row['table_name']) ?></td>
                    <td><?= $row['team1'] ?> - <?= htmlspecialchars($row['team1_name']) ?></td>
                    <td><?= $row['team2'] ?> - <?= htmlspecialchars($row['team2_name']) ?></td>
                    <td><?= $status_english ?></td>

                    <?php if ($can_edit): ?>
                        <td>
                            <button class="btn btn-sm btn-warning"
                                    onclick='openEditModal(<?= json_encode($row) ?>)'>
                                Edit
                            </button>
                            <a href="/event/matches.php?event_code=<?= $event_code ?>&delete=<?= $row['match_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this match?')">
                               Delete
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- OFFICIAL MATCHES -->
    <h4>Official Matches</h4>
    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Order</th>
                <th>Table</th>
                <th>Team 1</th>
                <th>Team 2</th>
                <th>Status</th>
                <?php if ($can_edit): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $officialMatches->fetch_assoc()): ?>
                <?php
                    $status_english = match($row['status']) {
                        "0" => "Upcoming",
                        "1" => "Playing",
                        "2" => "Completed",
                        "3" => "Scores Submitted",
                        default => "Unknown"
                    };
                ?>
                <tr>
                    <td><?= $row['match_order'] ?></td>
                    <td><?= htmlspecialchars($row['table_name']) ?></td>
                    <td><?= $row['team1'] ?> - <?= htmlspecialchars($row['team1_name']) ?></td>
                    <td><?= $row['team2'] ?> - <?= htmlspecialchars($row['team2_name']) ?></td>
                    <td><?= $status_english ?></td>

                    <?php if ($can_edit): ?>
                        <td>
                            <button class="btn btn-sm btn-warning"
                                    onclick='openEditModal(<?= json_encode($row) ?>)'>
                                Edit
                            </button>
                            <a href="/event/matches.php?event_code=<?= $event_code ?>&delete=<?= $row['match_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this match?')">
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
<div class="modal fade" id="matchModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">

      <input type="hidden" name="match_id" id="match_id">

      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Match</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Match Type</label>
            <select name="match_type" id="match_type" class="form-select">
                <option value="0">Practice</option>
                <option value="1">Official</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Match Order</label>
            <input type="number" name="match_order" id="match_order" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Table</label>
            <select name="table_id" id="table_id" class="form-select" required>
                <?php
                $tables->data_seek(0);
                while ($t = $tables->fetch_assoc()):
                ?>
                    <option value="<?= $t['table_id'] ?>"><?= htmlspecialchars($t['table_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Team 1</label>
            <select name="team1_id" id="team1_id" class="form-select" required>
                <?php
                $teams->data_seek(0);
                while ($tm = $teams->fetch_assoc()):
                ?>
                    <option value="<?= $tm['team_id'] ?>">
                        <?= $tm['team_num'] ?> - <?= htmlspecialchars($tm['team_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Team 2</label>
            <select name="team2_id" id="team2_id" class="form-select" required>
                <?php
                $teams->data_seek(0);
                while ($tm = $teams->fetch_assoc()):
                ?>
                    <option value="<?= $tm['team_id'] ?>">
                        <?= $tm['team_num'] ?> - <?= htmlspecialchars($tm['team_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="0">Upcoming</option>
                <option value="1">Playing</option>
                <option value="2">Completed</option>
                <option value="3">Scores Submitted</option>
            </select>
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
let modal = new bootstrap.Modal(document.getElementById('matchModal'));

function openEditModal(data) {
    document.getElementById('modalTitle').innerText = "Edit Match";

    document.getElementById('match_id').value = data.match_id;
    document.getElementById('match_type').value = data.match_type;
    document.getElementById('match_order').value = data.match_order;
    document.getElementById('table_id').value = data.table_id;
    document.getElementById('team1_id').value = data.team1_id;
    document.getElementById('team2_id').value = data.team2_id;
    document.getElementById('status').value = data.status;

    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Add Match";

    document.getElementById('match_id').value = 0;
    document.getElementById('match_type').value = 0;
    document.getElementById('match_order').value = "";
    document.getElementById('table_id').value = "";
    document.getElementById('team1_id').value = "";
    document.getElementById('team2_id').value = "";
    document.getElementById('status').value = 0;

    modal.show();
}
</script>

</body>
</html>
