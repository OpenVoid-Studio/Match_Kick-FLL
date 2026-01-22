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
$perm = get_permission("event/schedule", $event_id);
$is_superuser = !empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1;

if ($perm === false && !$is_superuser) {
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
    exit;
}

$can_edit = ($perm == 1 || $is_superuser);

// ---------------------------------------------------------
// Handle Add/Edit Schedule Block
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $schedule_id = intval($_POST["schedule_id"]);
    $title = trim($_POST["event_title"]);
    $desc = trim($_POST["event_description"]);
    $time = trim($_POST["event_time"]);

    // references_block: NULL, 0, or 1
    $ref = $_POST["references_block"];
    if ($ref === "none") {
        $ref = null;
    } else {
        $ref = intval($ref);
    }

    // Enforce only one practice and one official block
    if ($ref !== null) {
        $stmt = $conn->prepare("
            SELECT schedule_id 
            FROM schedule 
            WHERE event_id = ? AND references_block = ? AND schedule_id != ?
        ");
        $stmt->bind_param("iii", $event_id, $ref, $schedule_id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            echo "<h1>Error</h1><p>This event already has a block for this match type.</p>";
            exit;
        }
    }

    if ($schedule_id === 0) {
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO schedule (event_id, event_title, event_description, event_time, references_block)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssi", $event_id, $title, $desc, $time, $ref);
    } else {
        // Update
        $stmt = $conn->prepare("
            UPDATE schedule
            SET event_title = ?, event_description = ?, event_time = ?, references_block = ?
            WHERE schedule_id = ? AND event_id = ?
        ");
        $stmt->bind_param("sssiii", $title, $desc, $time, $ref, $schedule_id, $event_id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/schedule");
    exit;
}

// ---------------------------------------------------------
// Handle Delete
// ---------------------------------------------------------
if (isset($_GET["delete"]) && $can_edit) {
    $del_id = intval($_GET["delete"]);

    $stmt = $conn->prepare("DELETE FROM schedule WHERE schedule_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $del_id, $event_id);
    $stmt->execute();
    $stmt->close();

    header("Location: /event/$event_code/schedule");
    exit;
}

// ---------------------------------------------------------
// Fetch schedule items
// ---------------------------------------------------------
$stmt = $conn->prepare("
    SELECT *
    FROM schedule
    WHERE event_id = ?
    ORDER BY event_time ASC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$schedule_items = $stmt->get_result();
$stmt->close();

$practice_block_done = false;
$official_block_done = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($event["event_title"]) ?> – Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'nav.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Schedule</h3>

        <?php if ($can_edit): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">Add Schedule Item</button>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Time</th>
                <th>Title</th>
                <th>Description</th>
                <th>Match Block</th>
                <?php if ($can_edit): ?><th style="width: 150px;">Actions</th><?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = $schedule_items->fetch_assoc()): ?>
                <tr>
                    <td><?= date("g:i A", strtotime($row["event_time"])) ?></td>
                    <td><?= htmlspecialchars($row["event_title"]) ?></td>
                    <td><?= nl2br(htmlspecialchars($row["event_description"])) ?></td>

                    <td>
                        <?php
                            if ($row["references_block"] === null) { echo "" ;}
                            elseif ($row["references_block"] == 0) { echo "Practice Matches"; $practice_block_done = true; }
                            elseif ($row["references_block"] == 1) { echo "Official Matches"; $official_block_done = true; }
                        ?>
                    </td>

                    <?php if ($can_edit): ?>
                        <td>
                            <button 
                                class="btn btn-sm btn-warning"
                                onclick='openEditModal(<?= json_encode($row) ?>)'>
                                Edit
                            </button>

                            <a href="/event/schedule.php?event_code=<?= $event_code ?>&delete=<?= $row['schedule_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this schedule item?')">
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
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">

      <input type="hidden" name="schedule_id" id="schedule_id">

      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Edit Schedule Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="event_title" id="event_title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="event_description" id="event_description" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Start Time</label>
            <input type="datetime-local" name="event_time" id="event_time" class="form-control" required>
        </div>

        <div class="mb-3 <php if ($practice_block_done && $official_block_done) echo "d-none"; ?>
            <label class="form-label">Match Block</label>
            <select name="references_block" id="references_block" class="form-select">
                <option value="none">None</option>
                <?php if (!$practice_block_done): ?><option value="0">Practice Matches</option><?php endif; ?>
                <?php if (!$official_block_done): ?><option value="1">Official Matches</option><?php endif; ?>
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
let modal = new bootstrap.Modal(document.getElementById('scheduleModal'));

function openEditModal(data) {
    document.getElementById('modalTitle').innerText = "Edit Schedule Item";

    document.getElementById('schedule_id').value = data.schedule_id;
    document.getElementById('event_title').value = data.event_title;
    document.getElementById('event_description').value = data.event_description;
    document.getElementById('event_time').value = data.event_time.replace(" ", "T");

    document.getElementById('references_block').value = 
        data.references_block === null ? "none" : data.references_block;

    modal.show();
}

function openCreateModal() {
    document.getElementById('modalTitle').innerText = "Add Schedule Item";

    document.getElementById('schedule_id').value = 0;
    document.getElementById('event_title').value = "";
    document.getElementById('event_description').value = "";
    document.getElementById('event_time').value = "";
    document.getElementById('references_block').value = "none";

    modal.show();
}
</script>

</body>
</html>
