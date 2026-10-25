<?php
// Session-based sign-in for the student portal AND the admin portal. Credentials are
// verified against Moodle (see moodle_authenticate() in moodle.php); only the user's
// id, username, name and whether they are a site administrator are kept in the session.
//
// Admin = a Moodle site administrator (Moodle reports this as "userissiteadmin").
// Optional extra: set the environment variable ADMIN_USERNAMES (comma separated,
// e.g. "admin,jdelacruz") to also treat those Moodle usernames as admins.
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

/** Usernames that are always admins: ADMIN_USERNAMES as an environment variable, Apache SetEnv, or a PHP constant. */
function auth_admin_usernames(): array
{
    $raw = getenv("ADMIN_USERNAMES") ?: ($_SERVER["ADMIN_USERNAMES"] ?? (defined("ADMIN_USERNAMES") ? ADMIN_USERNAMES : ""));
    return array_values(array_filter(array_map("trim", explode(",", strtolower((string) $raw)))));
}

/**
 * Ask Moodle who this token belongs to (core_webservice_get_site_info).
 * Returns [info array|null, error message|null]. Never throws.
 */
function auth_fetch_site_info(string $token): array
{
    // Preferred: the portal's own Moodle client (same certificates and settings as the login)
    if (function_exists("moodle_get_json")) {
        $result = moodle_get_json("core_webservice_get_site_info", [], $token);
        if (!empty($result["ok"]) && is_array($result["data"] ?? null)) {
            return [$result["data"], null];
        }
        return [null, (string) ($result["message"] ?? "Moodle did not answer.")];
    }

    if (!function_exists("moodle_site_base")) {
        return [null, "moodle_site_base() is not available (functions/moodle.php was not loaded)."];
    }
    try {
        $url = rtrim(moodle_site_base(), "/") . "/webservice/rest/server.php";
        $body = http_build_query([
            "wstoken" => $token,
            "wsfunction" => "core_webservice_get_site_info",
            "moodlewsrestformat" => "json",
        ]);

        if (function_exists("curl_init")) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            $raw = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                return [null, "Could not reach Moodle at " . $url . ": " . $curl_error];
            }
        } else {
            $raw = @file_get_contents($url, false, stream_context_create(["http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded",
                "content" => $body,
                "timeout" => 8,
            ]]));
            if ($raw === false) {
                return [null, "Could not reach Moodle at " . $url . "."];
            }
        }

        $info = json_decode((string) $raw, true);
        if (!is_array($info)) {
            return [null, "Moodle did not answer with JSON."];
        }
        if (isset($info["exception"])) {
            return [null, "Moodle refused the request: " . ($info["errorcode"] ?? "") . " " . ($info["message"] ?? "")];
        }
        return [$info, null];
    } catch (Throwable $e) {
        return [null, $e->getMessage()];
    }
}

/**
 * Is this Moodle user an administrator?
 * Uses "is_admin" from moodle_authenticate() when it provides one; otherwise asks Moodle
 * (userissiteadmin) with the user's own token. Anything that goes wrong counts as "no".
 */
function auth_detect_admin(array $user): bool
{
    if (array_key_exists("is_admin", $user)) {
        return (bool) $user["is_admin"];
    }
    if (in_array(strtolower((string) ($user["username"] ?? "")), auth_admin_usernames(), true)) {
        return true;
    }

    $token = (string) ($user["token"] ?? "");
    if ($token === "") {
        error_log("Admin check: no Moodle token for " . ($user["username"] ?? "?") . " (moodle_authenticate() returned none).");
        return false;
    }

    [$info, $error] = auth_fetch_site_info($token);
    if ($error) {
        error_log("Admin check failed for " . ($user["username"] ?? "?") . ": " . $error);
        return false;
    }
    return !empty($info["userissiteadmin"]);
}

/** Is the signed-in user an administrator? */
function auth_is_admin(): bool
{
    $user = auth_user();
    return $user !== null && !empty($user["is_admin"]);
}

/**
 * Protect an admin page. Call at the very top, before any output and before the page
 * handles a POST. Not signed in => login page; signed in but not an admin => 403.
 */
function auth_require_admin(): void
{
    auth_require_login();

    // Signed in before the admin flag existed: work it out once from the saved token
    $user = auth_user();
    if ($user !== null && !array_key_exists("is_admin", $user)) {
        $_SESSION[AUTH_SESSION_KEY]["is_admin"] = auth_detect_admin($user + ["token" => (string) ($_SESSION["moodle_token"] ?? "")]);
    }
    if (auth_is_admin()) return;

    http_response_code(403);
    header("Cache-Control: no-store");
    $csrf = htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8");
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Admins only</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;text-align:center">'
        . '<h1 style="font-size:1.5rem">Admins only</h1>'
        . '<p>Your account does not have access to the admin portal.</p>'
        . '<p><a href="/">Back to the student portal</a></p>'
        . '<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="' . $csrf . '">'
        . '<button type="submit">Sign in with a different account</button></form>'
        . '</body></html>';
    exit;
}

function auth_sign_in(array $user, bool $remember): void
{
    auth_start_session();
    $is_admin = auth_detect_admin($user);
    session_regenerate_id(true); // prevents session fixation
    $_SESSION[AUTH_SESSION_KEY] = [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "name" => (string) $user["name"],
        "is_admin" => $is_admin,
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
