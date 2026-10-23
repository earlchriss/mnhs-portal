<?php
// JSON endpoint for the navbar notifications dropdown.
//   GET  /notifications.php            -> list + unread count
//   POST /notifications.php?action=read     (id=<notification id>)
//   POST /notifications.php?action=read_all
require_once __DIR__ . "/functions/moodle.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? "list";

if (!auth_user()) {
    respond(["ok" => false, "message" => "Please sign in again."], 401);
}

if ($method === "POST") {
    if (!auth_check_csrf($_SERVER["HTTP_X_CSRF_TOKEN"] ?? null)) {
        respond(["ok" => false, "message" => "Your session expired. Please refresh the page."], 403);
    }
    if ($action === "read_all") {
        $result = moodle_mark_notifications_read(null);
    } elseif ($action === "read") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        if (!$id) {
            respond(["ok" => false, "message" => "Missing notification id."], 400);
        }
        $result = moodle_mark_notifications_read($id);
    } else {
        respond(["ok" => false, "message" => "Unknown action."], 400);
    }
    respond(["ok" => $result["ok"], "message" => $result["message"] ?? null], $result["ok"] ? 200 : 502);
}

$feed = moodle_notifications(20);
if (!$feed["ok"]) {
    respond(["ok" => false, "message" => $feed["message"]], 502);
}
respond(["ok" => true] + $feed["data"]);
