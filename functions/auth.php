<?php
// Session-based sign-in for the student portal. Credentials are verified
// against Moodle (see moodle_authenticate() in moodle.php); only the student's
// id, username and name are kept in the session.
require_once __DIR__ . "/cache.php";
if (cache_redis()) {
    ini_set("session.save_handler", "redis");
    session_save_path("tcp://" . (getenv("REDIS_HOST") ?: "127.0.0.1") . ":" . (getenv("REDIS_PORT") ?: 6379));
} else {
    ini_set("session.save_handler", "files");
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "student_portal_sessions";
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    session_save_path($dir);
}
const AUTH_SESSION_KEY = "student_user";
const AUTH_REMEMBER_SECONDS = 2592000; // 30 days
const AUTH_MAX_FAILURES = 5;
const AUTH_LOCK_SECONDS = 60;

function auth_is_https(): bool
{
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["SERVER_PORT"] ?? "") === "443")
        || (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
}

function auth_cookie_params(int $lifetime): array
{
    return [
        "lifetime" => $lifetime,
        "path" => "/",
        "secure" => auth_is_https(),
        "httponly" => true,
        "samesite" => "Lax",
    ];
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "student_portal_sessions";
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    ini_set("session.save_handler", "files");
    session_save_path($dir);

    session_name("student_portal");
    session_set_cookie_params(auth_cookie_params(0));
    ini_set("session.gc_maxlifetime", (string) AUTH_REMEMBER_SECONDS);
    session_start();
}

/** The signed-in student ["id", "username", "name"], or null. */
function auth_user(): ?array
{
    auth_start_session();
    $user = $_SESSION[AUTH_SESSION_KEY] ?? null;
    return is_array($user) ? $user : null;
}

/** The signed-in student's Moodle token (server-side only), or null. */
function auth_user_token(): ?string
{
    auth_start_session();
    $token = $_SESSION["moodle_token"] ?? null;
    return is_string($token) && $token !== "" ? $token : null;
}

/** Redirect to the login page unless signed in. Call before any output. */
function auth_require_login(): void
{
    if (auth_user()) return;
    $next = $_SERVER["REQUEST_URI"] ?? "/";
    header("Location: /login/?next=" . rawurlencode($next));
    exit;
}

/** Only allow redirects to paths on this site (never another host). */
function auth_safe_next($next): string
{
    if (!is_string($next) || $next === "" || $next[0] !== "/") return "/";
    if (str_starts_with($next, "//") || str_contains($next, "\\") || preg_match('/[\r\n]/', $next)) return "/";
    if (str_starts_with($next, "/login")) return "/";
    return $next;
}

function auth_csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function auth_check_csrf(?string $token): bool
{
    auth_start_session();
    return !empty($_SESSION["csrf"]) && is_string($token) && hash_equals($_SESSION["csrf"], $token);
}

/** Seconds left on a temporary lockout after too many failed sign-ins, else 0. */
function auth_lock_remaining(): int
{
    auth_start_session();
    return max(0, (int) ($_SESSION["login_locked_until"] ?? 0) - time());
}

function auth_register_failure(): void
{
    auth_start_session();
    $_SESSION["login_failures"] = (int) ($_SESSION["login_failures"] ?? 0) + 1;
    if ($_SESSION["login_failures"] >= AUTH_MAX_FAILURES) {
        $_SESSION["login_failures"] = 0;
        $_SESSION["login_locked_until"] = time() + AUTH_LOCK_SECONDS;
    }
}

function auth_sign_in(array $user, bool $remember): void
{
    auth_start_session();
    session_regenerate_id(true); // prevents session fixation
    $_SESSION[AUTH_SESSION_KEY] = [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "name" => (string) $user["name"],
    ];
    // The student's own Moodle token lets us submit evaluations as them.
    // It stays in the server-side session and is never sent to the browser.
    if (!empty($user["token"])) {
        $_SESSION["moodle_token"] = (string) $user["token"];
    }
    unset($_SESSION["login_failures"], $_SESSION["login_locked_until"]);
    if ($remember) {
        $params = auth_cookie_params(AUTH_REMEMBER_SECONDS);
        setcookie(session_name(), session_id(), [
            "expires" => time() + AUTH_REMEMBER_SECONDS,
            "path" => $params["path"],
            "secure" => $params["secure"],
            "httponly" => true,
            "samesite" => "Lax",
        ]);
    }
}

function auth_sign_out(): void
{
    auth_start_session();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 3600,
        "path" => $params["path"],
        "secure" => $params["secure"],
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_destroy();
}
