<?php
// "Has anything new been posted on Moodle?" check, called by the page every few
// seconds (see src/useMoodleLive.js). Place it next to index.php.
require_once __DIR__ . "/functions/moodle.php";

ini_set("display_errors", "0"); // a PHP notice must never break the JSON
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

function changes_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_user()) {
    changes_reply(401, ["ok" => false, "message" => "Your session has ended. Please sign in again."]);
}

// Let the student's other requests (page loads, clicks) go ahead while we wait on
// Moodle. PHP otherwise holds a lock on the session until this script finishes.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $result = moodle_change_fingerprint();
} catch (Throwable $e) {
    error_log("changes.php: " . $e->getMessage());
    $result = ["ok" => false, "message" => "LMS could not be checked."];
}

if (!$result["ok"]) {
    changes_reply(502, ["ok" => false, "message" => $result["message"] ?? "LMS could not be checked."]);
}

changes_reply(200, ["ok" => true] + $result["data"]);
