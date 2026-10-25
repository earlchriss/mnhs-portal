<?php
// POST JSON {username, password, remember, next} -> {ok, redirect | message}
require_once __DIR__ . "/../functions/moodle.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    respond(["ok" => false, "message" => "Method not allowed."], 405);
}

auth_start_session();

$sentToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
if (!auth_check_csrf($sentToken)) {
    // "reason" tells us which part failed (it reveals nothing sensitive).
    if ($sentToken === "") {
        $reason = "no_token_sent";
    } elseif (!preg_match('/^[a-f0-9]{64}$/', $sentToken)) {
        $reason = "token_malformed"; // e.g. the literal text "undefined"
    } elseif (empty($_COOKIE[session_name()])) {
        $reason = "no_session_cookie";
    } elseif (empty($_SESSION["csrf"])) {
        $reason = "session_has_no_token";
    } else {
        $reason = "token_mismatch";
    }
    respond([
        "ok" => false,
        "reason" => $reason,
        "message" => "Your session expired. Please refresh the page and try again. ({$reason})",
    ], 403);
}

$wait = auth_lock_remaining();
if ($wait > 0) {
    respond(["ok" => false, "message" => "Too many failed attempts. Try again in {$wait} seconds."], 429);
}

$input = json_decode(file_get_contents("php://input") ?: "", true);
$input = is_array($input) ? $input : [];
$username = trim((string) ($input["username"] ?? ""));
$password = (string) ($input["password"] ?? "");
if ($username === "" || $password === "" || strlen($username) > 100 || strlen($password) > 200) {
    respond(["ok" => false, "message" => "Enter your username and password."], 400);
}

$result = moodle_authenticate($username, $password);
if (!$result["ok"]) {
    if ($result["code"] === 401) {
        auth_register_failure();
        usleep(500000); // slow down guessing
    }
    respond(["ok" => false, "message" => $result["message"]], $result["code"]);
}

auth_sign_in($result["data"], !empty($input["remember"]));

// Admins who did not come from a specific page land in the admin portal.
$redirect = auth_safe_next($input["next"] ?? null);
if ($redirect === "/" && auth_is_admin()) {
    $redirect = "/admin/";
}
respond(["ok" => true, "redirect" => $redirect]);
