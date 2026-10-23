<?php
// JSON endpoint for the Assignments page.
//   assignments_feed.php             -> the assignment list (+ change "version")
//   assignments_feed.php?fresh=1     -> same, but skips the 60-second cache
//   assignments_feed.php?pulse=1     -> only the change "version" (cheap check)
require_once __DIR__ . "/functions/moodle.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function feed_reply(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_user()) {
    feed_reply(["ok" => false, "code" => 401, "message" => "Your session has ended. Please sign in again."], 401);
}

$pulse = moodle_change_fingerprint();
$version = $pulse["ok"] ? ($pulse["data"]["version"] ?? null) : null;

if (isset($_GET["pulse"])) {
    $pulse["ok"]
        ? feed_reply(["ok" => true, "version" => $version])
        : feed_reply(["ok" => false, "message" => $pulse["message"] ?? "Could not check Moodle."], 502);
}

$feed = moodle_assignment_feed(isset($_GET["fresh"]));
if (!$feed["ok"]) {
    feed_reply(["ok" => false, "message" => $feed["message"] ?? "Assignments could not be loaded."], 502);
}
feed_reply(["ok" => true, "version" => $version, "data" => $feed["data"]]);
