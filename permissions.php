<?php
// permissions.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/event_pages.php';

/**
 * Returns:
 *   1 = user has edit permission
 *   0 = user has view permission
 *   false = user has no permission (page should block)
 */
function get_permission($page_slug, $event_id = null) {
    global $conn;

    if (!isset($_SESSION["user"])) {
        return false;
    }

    $username = $_SESSION["user"];

    // Superuser always has full permission
    if (!empty($_SESSION["is_superuser"]) && $_SESSION["is_superuser"] == 1) {
        return 1;
    }

    // Collect all roles for this user
    $roles = [];

    // 1. Global role via user_rank
    $stmt = $conn->prepare("SELECT user_rank FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $rank = $stmt->get_result()->fetch_assoc();

    if ($rank && $rank["user_rank"] > 0) {
        $roles[] = $rank["user_rank"];
    }

    // 2. Event-specific roles
    if ($event_id !== null) {
        $stmt = $conn->prepare("
            SELECT role_id 
            FROM user_event_roles 
            WHERE user_id = ? AND event_id = ?
        ");
        $stmt->bind_param("si", $username, $event_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $roles[] = $row["role_id"];
        }
    }

    if (empty($roles)) {
        return false;
    }

    // Build dynamic IN clause
    $placeholders = implode(",", array_fill(0, count($roles), "?"));
    $types = str_repeat("i", count($roles));

    $sql = "
        SELECT MAX(page_permission) AS perm
        FROM role_page_access
        WHERE page_slug = ?
        AND role_id IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);

    // Bind slug + roles
    $params = array_merge([$page_slug], $roles);
    $bind_types = "s" . $types;

    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $perm = $stmt->get_result()->fetch_assoc();

    // No row = no permission
    if ($perm["perm"] === null) {
        return false;
    }

    return intval($perm["perm"]); // 0 or 1
}

/**
 * Enforce that the user must have at least view permission.
 * If no permission row exists → block the page.
 */
function require_view_permission($page_slug, $event_id = null) {
    $perm = get_permission($page_slug, $event_id);

    if ($perm === false) {
        echo "<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>";
        exit;
    }

    return $perm; // return 0 or 1
}
