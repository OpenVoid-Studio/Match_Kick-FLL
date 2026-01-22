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
$perm = get_permission("event/scores", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle score saving
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $team_num = intval($_POST["team_num"]);
    $scores = $_POST["scores"] ?? [];
    $new_score = trim($_POST["new_score"]);

    // Get team_id
    $stmt = $conn->prepare("SELECT team_id FROM teams WHERE event_id = ? AND team_num = ?");
    $stmt->bind_param("ii", $event_id, $team_num);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($team) {
        $team_id = $team["team_id"];

        // Update existing rounds
        // Update existing rounds
        foreach ($scores as $round => $value) {

            // If empty, delete the score
            if ($value === "" || $value === null) {
                $stmt = $conn->prepare("
                    DELETE FROM scores 
                    WHERE event_id = ? AND team_id = ? AND round = ?
                ");
                $stmt->bind_param("iii", $event_id, $team_id, $round);
                $stmt->execute();
                $stmt->close();
                continue;
            }

            // Otherwise, save/update the score
            $value = intval($value);

            $stmt = $conn->prepare("
                INSERT INTO scores (event_id, team_id, round, score)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE score = VALUES(score)
            ");
            $stmt->bind_param("iiii", $event_id, $team_id, $round, $value);
            $stmt->execute();
            $stmt->close();
        }


        // Add new round
        if ($new_score !== "") {
            $new_score = intval($new_score);

            // Determine next round number
            $stmt = $conn->prepare("
                SELECT MAX(round) AS max_r
                FROM scores
                WHERE event_id = ? AND team_id = ?
            ");
            $stmt->bind_param("ii", $event_id, $team_id);
            $stmt->execute();
            $max = $stmt->get_result()->fetch_assoc()["max_r"] ?? 0;
            $stmt->close();

            $next_round = $max + 1;

            $stmt = $conn->prepare("
                INSERT INTO scores (event_id, team_id, round, score)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiii", $event_id, $team_id, $next_round, $new_score);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: /event/$event_code/scores");
    exit;
}

// ---------------------------------------------------------
// Fetch teams
// ---------------------------------------------------------
$teams = $conn->query("
    SELECT team_id, team_num, team_name
    FROM teams
    WHERE event_id = $event_id
    ORDER BY team_num ASC
");

// ---------------------------------------------------------
// Fetch scores
// ---------------------------------------------------------
$scores = [];
$max_round = 0;

$res = $conn->query("
    SELECT s.team_id, t.team_num, s.round, s.score
    FROM scores s
    JOIN teams t ON t.team_id = s.team_id
    WHERE s.event_id = $event_id
    ORDER BY t.team_num, s.round
");

while ($row = $res->fetch_assoc()) {
    $tn = $row["team_num"];
    $round = $row["round"];
    $scores[$tn][$round] = $row["score"];

    if ($round > $max_round) {
        $max_round = $round;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Scores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Score Editor</h3>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Team</th>
                <?php for ($i = 1; $i <= $max_round; $i++): ?>
                    <th>Round <?= $i ?></th>
                <?php endfor; ?>
                <?php if ($can_edit): ?>
                    <th style="width: 150px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php while ($team = $teams->fetch_assoc()): 
                $tn = $team['team_num'];
            ?>
                <tr>
                    <td><?= $tn ?> - <?= htmlspecialchars($team['team_name']) ?></td>

                    <?php for ($i = 1; $i <= $max_round; $i++): ?>
                        <td><?= $scores[$tn][$i] ?? "" ?></td>
                    <?php endfor; ?>

                    <?php if ($can_edit): ?>
                        <td>
                            <button 
                                class="btn btn-sm btn-warning"
                                onclick='openEditModal(<?= json_encode([
                                    "team_num" => $tn,
                                    "team_name" => $team["team_name"],
                                    "scores" => $scores[$tn] ?? []
                                ]) ?>)'
                            >
                                Edit
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="scoreModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <input type="hidden" name="team_num" id="team_num">

      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Scores</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="scoreFields"></div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
      
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let modal = new bootstrap.Modal(document.getElementById('scoreModal'));

function openEditModal(data) {
    document.getElementById('modalTitle').innerText = "Edit Scores for Team " + data.team_num;
    document.getElementById('team_num').value = data.team_num;

    let container = document.getElementById('scoreFields');
    container.innerHTML = "";

    let scores = data.scores;
    let rounds = Object.keys(scores).length;

    // Existing rounds
    for (let r = 1; r <= rounds; r++) {
        container.innerHTML += `
            <div class="mb-3">
                <label class="form-label">Round ${r}</label>
                <input type="number" class="form-control" name="scores[${r}]" value="${scores[r] ?? ''}">
            </div>
        `;
    }

    // New score
    container.innerHTML += `
        <div class="mb-3">
            <label class="form-label">Add New Score</label>
            <input type="number" class="form-control" name="new_score">
        </div>
    `;

    modal.show();
}
</script>

</body>
</html>
