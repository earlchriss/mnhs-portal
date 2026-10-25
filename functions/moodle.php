<?php

require_once __DIR__ . "/auth.php";

const MOODLE_TIMEZONE = "Asia/Manila"; // Philippine time (UTC+8)
const MOODLE_MAX_PARALLEL = 8;
const MOODLE_CONTENTS_TTL = 300;   // course contents (same for every student)
const MOODLE_TEACHERS_TTL = 3600;  // teachers of a course
const MOODLE_FEED_TTL = 60;        // finished assignment list, per student
const MOODLE_STUDENT_CONTENTS_TTL = 10; // a student's own course contents: short, so new posts show up quickly
const MOODLE_PULSE_TTL = 5;        // "has anything changed?" answer, per student

function moodle_site_base(): string
{
    $rest = getenv("MOODLE_REST_URL") ?: "https://lms.mabolonationalhs.me/webservice/rest/server.php";
    return preg_replace('#/webservice/rest/server\.php.*$#', "", $rest);
}

function moodle_curl_options(int $connectMs, int $timeoutMs): array
{
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT_MS => $connectMs,
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_HTTPHEADER => ["Accept: application/json"],
    ];
    $caBundle = getenv("MOODLE_CA_BUNDLE") ?: "C:\\xampp\\apache\\bin\\curl-ca-bundle.crt";
    if (is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    return $options;
}

/**
 * Check a student's username/password against Moodle (login/token.php), then
 * read their identity with their own short-lived token. The token is not kept.
 * On failure "code" is 401 (bad credentials) or 502 (Moodle/service problem).
 */
function moodle_authenticate(string $username, string $password): array
{
    $service = getenv("MOODLE_LOGIN_SERVICE") ?: "moodle_mobile_app";
    $request = curl_init(moodle_site_base() . "/login/token.php");
    curl_setopt_array($request, moodle_curl_options(3000, 8000) + [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            "username" => $username,
            "password" => $password,
            "service" => $service,
        ]),
    ]);
    $body = curl_exec($request);
    $failed = $body === false || curl_error($request);
    curl_close($request);
    if ($failed) {
        return ["ok" => false, "code" => 502, "message" => "Moodle is temporarily unavailable. Please try again later."];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ["ok" => false, "code" => 502, "message" => "Moodle returned an invalid response. Please try again later."];
    }
    if (empty($data["token"])) {
        if (($data["errorcode"] ?? "") === "invalidlogin") {
            return ["ok" => false, "code" => 401, "message" => "Incorrect username or password."];
        }
        return ["ok" => false, "code" => 502, "message" => "Sign in is unavailable right now. Please contact support."];
    }

    $info = moodle_get_json("core_webservice_get_site_info", [], $data["token"]);
    if (!$info["ok"] || empty($info["data"]["userid"])) {
        return ["ok" => false, "code" => 502, "message" => "Your account could not be loaded. Please contact support."];
    }
    return ["ok" => true, "data" => [
        "id" => (int) $info["data"]["userid"],
        "username" => (string) ($info["data"]["username"] ?? $username),
        "name" => trim((string) ($info["data"]["fullname"] ?? $username)),
        "token" => (string) $data["token"], // kept server-side in the session only
        // Moodle site administrators may use the admin portal (see auth_require_admin())
        "is_admin" => !empty($info["data"]["userissiteadmin"]),
    ]];
}

/**
 * Call a Moodle function AS the signed-in student (needed to answer feedback
 * forms). Uses the student's own token from sign-in, never the site token.
 */
function moodle_user_json(string $function, array $params): array
{
    $token = auth_user_token();
    if (!$token) {
        return ["ok" => false, "message" => "Please sign out and sign in again to continue."];
    }
    $result = moodle_get_json($function, $params, $token);
    if (!$result["ok"] && stripos($result["message"], "access control") !== false) {
        return ["ok" => false, "message" => "Enable {$function} for the Moodle service used at sign-in (moodle_mobile_app by default)."];
    }
    return $result;
}

function moodle_format_time(int $timestamp, string $format = "Y-m-d H:i"): string
{
    $date = new DateTime("@{$timestamp}");
    $date->setTimezone(new DateTimeZone(MOODLE_TIMEZONE));
    return $date->format($format);
}

/**
 * Map of course-module id => completion timestamp for activities the student
 * has completed in a course. Best effort: returns [] if completion tracking
 * is disabled or the token lacks access to the function.
 */


/**
 * Returns the submission/finish timestamp (int) if the student has handed in
 * this assignment or finished a quiz attempt, otherwise null. Best effort.
 */

/**
 * The Moodle user id of the student who is signed in to the portal.
 * Returns ["ok" => true, "data" => <id>] or an error array.
 */
function moodle_resolve_user_id(): array
{
    $user = auth_user();
    if (!$user) {
        return ["ok" => false, "message" => "Please sign in to continue."];
    }
    return ["ok" => true, "data" => $user["id"]];
}

/**
 * The student's Moodle notifications (newest first) plus the unread count.
 * Needs message_popup_get_popup_notifications enabled on the web-service token.
 */
function moodle_notifications(int $limit = 20): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $userId = $user["data"];

    $token = auth_user_token();
    if (!$token) {
        return ["ok" => false, "message" => "Please sign out and sign in again to continue."];
    }

    $call = fn(int $read, int $num) => [
        "function" => "core_message_get_messages",
        "params" => [
            "useridto" => $userId,
            "useridfrom" => 0,
            "type" => "notifications",
            "read" => $read,
            "newestfirst" => 1,
            "limitfrom" => 0,
            "limitnum" => $num,
        ],
        "token" => $token,
    ];
    $results = moodle_get_json_multi([
        "unread" => $call(0, 100),
        "read" => $call(1, max(1, $limit)),
    ]);

    foreach (["unread", "read"] as $part) {
        if (!$results[$part]["ok"]) {
            $message = (string) ($results[$part]["message"] ?? "");
            if (stripos($message, "access control") !== false) {
                return ["ok" => false, "message" => "Enable core_message_get_messages for the Moodle service used at sign-in (moodle_mobile_app by default)."];
            }
            return $results[$part];
        }
    }

    $clean = fn($s) => trim(html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, "UTF-8"));

    $items = [];
    foreach (["unread" => false, "read" => true] as $part => $isRead) {
        foreach ($results[$part]["data"]["messages"] ?? [] as $n) {
            $text = $clean($n["smallmessage"] ?? "") ?: $clean($n["fullmessage"] ?? "") ?: $clean($n["text"] ?? "");
            $url = $n["contexturl"] ?? null;
            if ($url && !preg_match('#^https?://#i', $url)) {
                $url = null; // only ever link to http(s) URLs
            }
            $created = (int) ($n["timecreated"] ?? 0);
            $items[] = [
                "id" => (int) $n["id"],
                "subject" => $clean($n["subject"] ?? "") ?: "Notification",
                "text" => $text,
                "url" => $url,
                "read" => $isRead,
                "time" => $created ? moodle_format_time($created, "M j, Y g:i A") : "",
                "_ts" => $created,
            ];
        }
    }

    usort($items, fn($a, $b) => $b["_ts"] <=> $a["_ts"]);
    $unread = count(array_filter($items, fn($i) => !$i["read"]));
    $items = array_slice($items, 0, $limit);
    foreach ($items as &$item) unset($item["_ts"]);
    unset($item);

    return ["ok" => true, "data" => ["notifications" => $items, "unread" => $unread]];
}

/**
 * Mark one notification (or all, when $id is null) as read. The id must belong
 * to the student's own notifications.
 */
function moodle_mark_notifications_read(?int $id): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $userId = $user["data"];

    if ($id === null) {
        return moodle_user_json("core_message_mark_all_notifications_as_read", [
            "useridto" => $userId,
            "useridfrom" => 0,
        ]);
    }

    // Never trust an id from the browser: confirm it is one of this student's.
    $own = moodle_notifications(100);
    if (!$own["ok"]) return $own;
    $ids = array_column($own["data"]["notifications"], "id");
    if (!in_array($id, $ids, true)) {
        return ["ok" => false, "message" => "Notification not found."];
    }
    return moodle_user_json("core_message_mark_notification_read", [
        "notificationid" => $id,
        "timeread" => time(),
    ]);
}

/** Teachers (editing and non-editing) of a course as [["id" => , "name" => ], ...]. */


function moodle_service_icon(string $name): string
{
    $name = strtolower($name);
    $map = [
        "library" => "fa-book",
        "guidance" => "fa-comments",
        "counsel" => "fa-comments",
        "clinic" => "fa-notes-medical",
        "health" => "fa-notes-medical",
        "canteen" => "fa-utensils",
        "cafeteria" => "fa-utensils",
        "registrar" => "fa-id-card",
        "cashier" => "fa-cash-register",
        "security" => "fa-shield-halved",
        "principal" => "fa-user-tie",
    ];
    foreach ($map as $word => $icon) {
        if (str_contains($name, $word)) return $icon;
    }
    return "fa-clipboard-list";
}

/** A visible Moodle feedback activity the student may open. */
function moodle_is_open_feedback(array $module): bool
{
    return strtolower($module["modname"] ?? "") === "feedback"
        && !(isset($module["visible"]) && !$module["visible"])
        && !(isset($module["uservisible"]) && !$module["uservisible"]);
}

/**
 * Has the signed-in student already submitted this feedback form? Asks Moodle
 * directly, so it works even when activity completion isn't set up.
 */
function moodle_feedback_submitted(int $feedbackId): bool
{
    if ($feedbackId <= 0) return false;
    $access = moodle_user_json("mod_feedback_get_feedback_access_information", ["feedbackid" => $feedbackId]);
    return $access["ok"] && !empty($access["data"]["isalreadysubmitted"]);
}


/* ------------------------------------------------------------------ */
/* Cache: Redis when available, temp files otherwise                    */
/* ------------------------------------------------------------------ */

const MOODLE_CACHE_PREFIX = "portal:moodle:";

/**
 * The shared Redis connection, or null when Redis is not installed, not running
 * or too slow. Optional settings (set them in Apache like MOODLE_WSTOKEN):
 * REDIS_HOST (127.0.0.1), REDIS_PORT (6379), REDIS_PASSWORD, REDIS_DB (0).
 * One failed attempt per request: after it, the file cache is used for the rest
 * of that request so a stopped Redis never slows the page down more than 0.25 s.
 */
function moodle_redis(): ?Redis
{
    static $redis = null;
    static $tried = false;
    if ($tried) return $redis;
    $tried = true;
    if (!class_exists("Redis")) return null;
    try {
        $client = new Redis();
        $host = getenv("REDIS_HOST") ?: "127.0.0.1";
        $port = (int) (getenv("REDIS_PORT") ?: 6379);
        if (!$client->connect($host, $port, 0.25)) return null;
        $client->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
        $password = getenv("REDIS_PASSWORD");
        if ($password) $client->auth($password);
        $client->select((int) (getenv("REDIS_DB") ?: 0));
        $redis = $client;
    } catch (Throwable $e) {
        $redis = null;
    }
    return $redis;
}

function moodle_cache_file(string $key): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "portal_moodle_cache";
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . DIRECTORY_SEPARATOR . sha1($key) . ".json";
}

/** Cached value, or null when missing/expired. */
function moodle_cache_get(string $key): ?array
{
    $redis = moodle_redis();
    if ($redis) {
        try {
            $raw = $redis->get(MOODLE_CACHE_PREFIX . $key);
            if (!is_string($raw)) return null;
            $value = json_decode($raw, true);
            return is_array($value) ? $value : null;
        } catch (Throwable $e) {
            // Redis went away mid-request: use the files below
        }
    }

    $file = moodle_cache_file($key);
    $raw = is_file($file) ? @file_get_contents($file) : false;
    if ($raw === false) return null;
    $entry = json_decode($raw, true);
    if (!is_array($entry) || ($entry["exp"] ?? 0) < time() || !is_array($entry["v"] ?? null)) {
        return null;
    }
    return $entry["v"];
}

function moodle_cache_set(string $key, array $value, int $ttl): void
{
    $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return;

    $redis = moodle_redis();
    if ($redis) {
        try {
            if ($redis->setex(MOODLE_CACHE_PREFIX . $key, max(1, $ttl), $json)) return; // Redis expires it itself
        } catch (Throwable $e) {
            // fall through to the file cache
        }
    }

    $file = moodle_cache_file($key);
    $tmp = $file . "." . bin2hex(random_bytes(4)) . ".tmp";
    if (@file_put_contents($tmp, json_encode(["exp" => time() + $ttl, "v" => $value])) !== false) {
        @rename($tmp, $file); // atomic, so readers never see half a file
    }
}

/** Forget everything cached (Redis and files). Returns how many entries were removed. */
function moodle_cache_clear(): int
{
    $removed = 0;
    $redis = moodle_redis();
    if ($redis) {
        try {
            $it = null;
            while (($keys = $redis->scan($it, MOODLE_CACHE_PREFIX . "*", 200)) !== false) {
                if ($keys) $removed += (int) $redis->del($keys);
                if ($it === 0 || $it === null) break;
            }
        } catch (Throwable $e) {
            // ignore, the files are cleared below
        }
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "portal_moodle_cache";
    foreach (glob($dir . DIRECTORY_SEPARATOR . "*.json") ?: [] as $file) {
        if (@unlink($file)) $removed++;
    }
    return $removed;
}

/* ------------------------------------------------------------------ */
/* HTTP: one call, or many calls in parallel                            */
/* ------------------------------------------------------------------ */

function moodle_decode_response($body, string $error, int $status): array
{
    if ($body === false || $body === null || $error) {
        return ["ok" => false, "message" => "Moodle is temporarily unavailable. Assignments could not be synchronized."];
    }
    $data = json_decode($body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return ["ok" => false, "message" => "Moodle returned an invalid response (HTTP {$status})."];
    }
    if (is_array($data) && isset($data["exception"])) {
        if (($data["errorcode"] ?? "") === "invalidtoken") {
            return ["ok" => false, "message" => "The Moodle web-service token is invalid or expired. Update MOODLE_WSTOKEN in Apache and restart Apache."];
        }
        return ["ok" => false, "message" => $data["message"] ?? "Moodle returned an invalid response (HTTP {$status})."];
    }
    return ["ok" => true, "data" => $data]; // may be a bool/int for simple functions
}

function moodle_get_json(string $function, array $params, ?string $tokenOverride = null): array
{
    $call = ["function" => $function, "params" => $params];
    if ($tokenOverride) $call["token"] = $tokenOverride;
    return moodle_get_json_multi(["one" => $call])["one"];
}

/**
 * Run many Moodle calls at the same time.
 * $calls = [key => ["function" => "...", "params" => [...], "token" => optional], ...]
 * Returns [key => same ["ok", "data"|"message"] array moodle_get_json() returns].
 */
function moodle_get_json_multi(array $calls): array
{
    $results = [];
    $baseUrl = getenv("MOODLE_REST_URL") ?: "https://lms.mabolonationalhs.me/webservice/rest/server.php";
    $siteToken = getenv("MOODLE_WSTOKEN");

    foreach (array_chunk($calls, MOODLE_MAX_PARALLEL, true) as $batch) {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($batch as $key => $call) {
            $token = $call["token"] ?? $siteToken;
            if (!$token) {
                $results[$key] = ["ok" => false, "message" => "Moodle synchronization needs MOODLE_WSTOKEN on the server."];
                continue;
            }
            $query = array_merge($call["params"] ?? [], [
                "wstoken" => $token,
                "wsfunction" => $call["function"],
                "moodlewsrestformat" => "json",
            ]);
            $handle = curl_init($baseUrl . "?" . http_build_query($query));
            curl_setopt_array($handle, moodle_curl_options(2000, 5000));
            curl_multi_add_handle($multi, $handle);
            $handles[$key] = $handle;
        }

        do {
            $state = curl_multi_exec($multi, $running);
            if ($running) curl_multi_select($multi, 1.0);
        } while ($running && $state === CURLM_OK);

        foreach ($handles as $key => $handle) {
            $body = curl_multi_getcontent($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            $results[$key] = moodle_decode_response($error ? false : $body, $error, $status);
        }
        curl_multi_close($multi);
    }
    return $results;
}
 
/* ------------------------------------------------------------------ */
/* Cached, batched building blocks                                      */
/* ------------------------------------------------------------------ */

/** Course contents for many courses: cached for everyone, misses fetched in parallel. */
function moodle_course_contents_multi(array $courseIds): array
{
    $out = [];
    $calls = [];
    foreach ($courseIds as $id) {
        $cached = moodle_cache_get("contents:v2:$id");
        if ($cached !== null) {
            $out[$id] = ["ok" => true, "data" => $cached];
        } else {
            $calls[$id] = ["function" => "core_course_get_contents", "params" => ["courseid" => $id]];
        }
    }
    foreach (moodle_get_json_multi($calls) as $id => $result) {
        if ($result["ok"]) moodle_cache_set("contents:v2:$id", $result["data"], MOODLE_CONTENTS_TTL);
        $out[$id] = $result;
    }
    return $out;
}

/** Teachers for many courses: [courseId => [["id", "name"], ...]]. Cached for 1 hour. */
function moodle_course_teachers_multi(array $courseIds, ?string $token = null): array
{
    $out = [];
    $calls = [];
    foreach ($courseIds as $id) {
        $cached = moodle_cache_get("teachers:$id");
        if ($cached !== null) {
            $out[$id] = $cached;
        } else {
            $calls[$id] = ["function" => "core_enrol_get_enrolled_users", "params" => ["courseid" => $id]];
            if ($token) $calls[$id]["token"] = $token; // read as this student instead of the shared site token
        }
    }
    foreach (moodle_get_json_multi($calls) as $id => $result) {
        $teachers = [];
        if ($result["ok"]) {
            foreach ($result["data"] as $person) {
                $roles = array_column($person["roles"] ?? [], "shortname");
                if (array_intersect($roles, ["editingteacher", "teacher"])) {
                    $teachers[] = [
                        "id" => (int) $person["id"],
                        "name" => trim((string) ($person["fullname"] ?? "Teacher")),
                    ];
                }
            }
            moodle_cache_set("teachers:$id", $teachers, MOODLE_TEACHERS_TTL);
        }
        $out[$id] = $teachers;
    }
    return $out;
}

/** Teachers of one course (kept for any code that still calls it). */
function moodle_course_teachers($courseId): array
{
    return moodle_course_teachers_multi([$courseId])[$courseId] ?? [];
}

/** Completed activities from a completion-status result: [cmid => timecompleted]. */
function moodle_parse_completed(array $result): array
{
    if (!$result["ok"]) return [];
    $completed = [];
    foreach ($result["data"]["statuses"] ?? [] as $status) {
        // 1 = complete, 2 = complete (pass); 0 = incomplete, 3 = complete (fail)
        if (in_array((int) ($status["state"] ?? 0), [1, 2], true)) {
            $completed[(int) $status["cmid"]] = (int) ($status["timecompleted"] ?? 0);
        }
    }
    return $completed;
}

function moodle_completed_modules($courseId, $userId): array
{
    return moodle_parse_completed(moodle_get_json("core_completion_get_activities_completion_status", [
        "courseid" => $courseId,
        "userid" => $userId,
    ]));
}

/** The Moodle call that tells whether the student handed in / finished this module, or null. */
function moodle_submission_call(string $type, array $module, $userId): ?array
{
    $instance = $module["instance"] ?? null;
    if (!$instance) return null;
    if ($type === "assign") {
        return ["function" => "mod_assign_get_submission_status", "params" => ["assignid" => $instance, "userid" => $userId]];
    }
    if ($type === "quiz") {
        return ["function" => "mod_quiz_get_user_attempts", "params" => ["quizid" => $instance, "userid" => $userId, "status" => "finished"]];
    }
    return null;
}

/** Submission/finish timestamp from the result of moodle_submission_call(), or null. */
function moodle_submission_time_from(string $type, array $result): ?int
{
    if (!$result["ok"]) return null;
    if ($type === "assign") {
        $submission = $result["data"]["lastattempt"]["submission"] ?? null;
        if ($submission && ($submission["status"] ?? "") === "submitted") {
            return (int) ($submission["timemodified"] ?? 0);
        }
        return null;
    }
    if ($type === "quiz") {
        $latest = null;
        foreach ($result["data"]["attempts"] ?? [] as $attempt) {
            $finished = (int) ($attempt["timefinish"] ?? 0);
            if ($finished > 0 && ($latest === null || $finished > $latest)) {
                $latest = $finished;
            }
        }
        return $latest;
    }
    return null;
}

/** Single-module version (kept for any code that still calls it). */
function moodle_submission_time(string $type, array $module, $userId): ?int
{
    $call = moodle_submission_call($type, $module, $userId);
    if (!$call) return null;
    return moodle_submission_time_from($type, moodle_get_json_multi(["x" => $call])["x"]);
}

/* ------------------------------------------------------------------ */
/* Home page: evaluation forms                                          */
/* ------------------------------------------------------------------ */

/**
 * Course contents read AS the signed-in student, cached per student. Reading as
 * the student means hidden or restricted activities match what they can open,
 * and it does not depend on what the shared site-token account is allowed to see.
 */
function moodle_student_contents_multi(int $userId, array $courseIds): array
{
    $token = auth_user_token();
    $out = [];
    $calls = [];
    foreach ($courseIds as $id) {
        $cached = moodle_cache_get("contents:u$userId:$id");
        if ($cached !== null) {
            $out[$id] = ["ok" => true, "data" => $cached];
        } else {
            $calls[$id] = [
                "function" => "core_course_get_contents",
                "params" => ["courseid" => $id],
                "token" => $token,
            ];
        }
    }
    foreach (moodle_get_json_multi($calls) as $id => $result) {
        if ($result["ok"]) moodle_cache_set("contents:u$userId:$id", $result["data"], MOODLE_STUDENT_CONTENTS_TTL);
        $out[$id] = $result;
    }
    return $out;
}

/**
 * A short fingerprint of everything on Moodle that this student can see: the
 * activities, labels and files of every course and of the site home, the forum
 * discussion counts, and the newest notification. It changes whenever something
 * is posted, edited, hidden or removed. The page asks for it every few seconds
 * and reloads its data when it changes. Cached for a few seconds, so many tabs
 * and many polls cost Moodle only one lookup.
 *
 * Returns ["ok" => true, "data" => ["version", "unread", "latest"]] or an error array.
 */
function moodle_change_fingerprint(): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $userId = $user["data"];

    $cached = moodle_cache_get("pulse:$userId");
    if ($cached !== null) return ["ok" => true, "data" => $cached];

    $courses = moodle_user_json("core_enrol_get_users_courses", ["userid" => $userId]);
    if (!$courses["ok"]) return $courses;

    $ids = [1]; // 1 = the site home (announcements and service forms)
    foreach ($courses["data"] as $course) {
        if (isset($course["visible"]) && !$course["visible"]) continue;
        $ids[] = (int) $course["id"];
    }
    $ids = array_values(array_unique($ids));

    // Every section, activity, label and file the student can see.
    $contents = moodle_student_contents_multi($userId, $ids);
    $parts = [];
    foreach ($ids as $id) {
        if (empty($contents[$id]["ok"])) continue;
        foreach ($contents[$id]["data"] as $section) {
            $parts[] = [$id, (int) ($section["id"] ?? 0), !empty($section["visible"]), md5((string) ($section["summary"] ?? ""))];
            foreach ($section["modules"] ?? [] as $module) {
                $files = [];
                foreach ($module["contents"] ?? [] as $file) {
                    $files[] = [$file["filename"] ?? "", (int) ($file["timemodified"] ?? 0)];
                }
                $parts[] = [
                    $id,
                    (int) ($module["id"] ?? 0),
                    $module["modname"] ?? "",
                    $module["name"] ?? "",
                    !empty($module["visible"]),
                    !empty($module["uservisible"]),
                    $module["dates"] ?? [],
                    md5((string) ($module["description"] ?? "")),
                    $files,
                ];
            }
        }
    }

    // New forum discussions (site announcements and course forums). Best effort.
    $forums = [];
    $forumList = moodle_user_json("mod_forum_get_forums_by_courses", ["courseids" => $ids]);
    if ($forumList["ok"]) {
        foreach ($forumList["data"] as $forum) {
            $forums[] = [(int) ($forum["id"] ?? 0), (int) ($forum["numdiscussions"] ?? 0), (int) ($forum["timemodified"] ?? 0)];
        }
    }

    // The newest Moodle notification. Best effort.
    $latest = null;
    $unread = 0;
    $notes = moodle_notifications(1);
    if ($notes["ok"]) {
        $unread = (int) $notes["data"]["unread"];
        $first = $notes["data"]["notifications"][0] ?? null;
        if ($first) {
            $latest = ["id" => (int) $first["id"], "subject" => $first["subject"]];
        }
    }

    // The unread count is left out on purpose: reading a notification is not "new content".
    $data = [
        "version" => substr(sha1((string) json_encode([$parts, $forums, $latest["id"] ?? 0], JSON_INVALID_UTF8_SUBSTITUTE)), 0, 16),
        "unread" => $unread,
        "latest" => $latest,
    ];
    moodle_cache_set("pulse:$userId", $data, MOODLE_PULSE_TTL);
    return ["ok" => true, "data" => $data];
}
const MOODLE_FORMS_TTL = 30; // seconds: short, because "submitted" status changes

/** Evaluation forms for the signed-in student, cached per student in Redis. */
function moodle_evaluation_forms(bool $fresh = false): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $key = "forms:" . $user["data"];

    if (!$fresh) {
        $cached = moodle_cache_get($key);
        if ($cached !== null) return ["ok" => true, "data" => $cached];
    }

    $result = moodle_evaluation_forms_uncached();
    if ($result["ok"]) {
        moodle_cache_set($key, $result["data"], MOODLE_FORMS_TTL); // never cache errors
    }
    return $result;
}

/**
 * Drop a student's cached feed, forms and change fingerprint. Call it right
 * after the student submits an evaluation, so the page shows "completed"
 * immediately instead of waiting for the cache to expire.
 */
function moodle_forget_student(int $userId): void
{
    $redis = moodle_redis();
    foreach (["forms:$userId", "feed:$userId", "pulse:$userId"] as $key) {
        if ($redis) {
            try {
                $redis->del(MOODLE_CACHE_PREFIX . $key);
            } catch (Throwable $e) {
            }
        }
        @unlink(moodle_cache_file($key));
    }
}
function moodle_evaluation_forms_uncached(): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $userId = $user["data"];

    // Everything on this page is read AS the student (their own sign-in token).
    // The shared site token belongs to a non-admin account, and Moodle silently
    // hides the student's courses from it, which left the dropdown empty.
    $userToken = auth_user_token();
    $courses = moodle_user_json("core_enrol_get_users_courses", ["userid" => $userId]);
    if (!$courses["ok"]) return $courses;

    $visible = [];
    foreach ($courses["data"] as $course) {
        if (
            (isset($course["visible"]) && !$course["visible"])
            || (isset($course["uservisible"]) && !$course["uservisible"])
        ) {
            continue;
        }
        $visible[(int) $course["id"]] = $course;
    }

    // 1) contents of every course and of the front page, all at once
    $contents = moodle_student_contents_multi($userId, array_merge(array_keys($visible), [1]));
    // A course that cannot be read is skipped so it cannot hide the others;
    // the error is only reported when none of the courses could be read.
    $readable = [];
    $firstError = null;
    foreach ($visible as $id => $course) {
        if ($contents[$id]["ok"]) {
            $readable[$id] = $course;
        } elseif ($firstError === null) {
            $firstError = $contents[$id];
        }
    }
    if ($visible && !$readable && $firstError !== null) return $firstError;
    $visible = $readable;

    $pattern = getenv("MOODLE_FACULTY_EVAL_PATTERN") ?: '/teacher|faculty|instructor|evaluation/i';
    $evaluations = []; // courseId => feedback module
    foreach ($visible as $id => $course) {
        foreach ($contents[$id]["data"] as $section) {
            foreach ($section["modules"] ?? [] as $module) {
                if (moodle_is_open_feedback($module) && preg_match($pattern, strip_tags($module["name"] ?? ""))) {
                    $evaluations[$id] = $module;
                    break 2;
                }
            }
        }
    }

    $serviceModules = [];
    if ($contents[1]["ok"]) {
        foreach ($contents[1]["data"] as $section) {
            foreach ($section["modules"] ?? [] as $module) {
                if (moodle_is_open_feedback($module)) $serviceModules[] = $module;
            }
        }
    }

    // 2) everything that depends on the student: completion + "already submitted?" (all at once)
    $calls = [];
    foreach (array_merge(array_keys($evaluations), $serviceModules ? [1] : []) as $id) {
        $calls["done:$id"] = [
            "function" => "core_completion_get_activities_completion_status",
            "params" => ["courseid" => $id, "userid" => $userId],
            "token" => $userToken,
        ];
    }
    if ($userToken) {
        foreach ($evaluations as $id => $module) {
            if (!empty($module["instance"])) {
                $calls["sub:cm" . $module["id"]] = [
                    "function" => "mod_feedback_get_feedback_access_information",
                    "params" => ["feedbackid" => (int) $module["instance"]],
                    "token" => $userToken,
                ];
            }
        }
        foreach ($serviceModules as $module) {
            if (!empty($module["instance"])) {
                $calls["sub:cm" . $module["id"]] = [
                    "function" => "mod_feedback_get_feedback_access_information",
                    "params" => ["feedbackid" => (int) $module["instance"]],
                    "token" => $userToken,
                ];
            }
        }
    }
    $answers = moodle_get_json_multi($calls);
    $teachers = moodle_course_teachers_multi(array_keys($evaluations), $userToken);

    $isSubmitted = function (array $module, array $completed) use ($answers): bool {
        if (isset($completed[(int) $module["id"]])) return true;
        $access = $answers["sub:cm" . $module["id"]] ?? null;
        return $access && $access["ok"] && !empty($access["data"]["isalreadysubmitted"]);
    };

    $faculty = [];
    foreach ($evaluations as $id => $module) {
        $course = $visible[$id];
        $completed = moodle_parse_completed($answers["done:$id"] ?? ["ok" => false]);
        $status = $isSubmitted($module, $completed) ? "completed" : "pending";
        $courseTeachers = $teachers[$id] ?? [];
        if (!$courseTeachers) {
            $courseTeachers = [[
                "id" => 0,
                "name" => trim((string) ($course["fullname"] ?? "Course instructor")),
            ]];
        }
        foreach ($courseTeachers as $teacher) {
            $faculty[] = [
                "id" => $id . "-" . $teacher["id"],
                "instructor" => $teacher["name"],
                "code" => (string) ($course["shortname"] ?? $course["fullname"]),
                "course" => (string) ($course["fullname"] ?? ""),
                "status" => $status,
                "cmid" => (int) $module["id"],
                "instance" => (int) ($module["instance"] ?? 0),
                "url" => $module["url"] ?? null,
            ];
        }
    }

    $services = [];
    $frontCompleted = moodle_parse_completed($answers["done:1"] ?? ["ok" => false]);
    foreach ($serviceModules as $module) {
        $title = trim(strip_tags($module["name"] ?? "Service evaluation"));
        $services[] = [
            "id" => (string) $module["id"],
            "title" => $title,
            "icon" => moodle_service_icon($title),
            "status" => $isSubmitted($module, $frontCompleted) ? "completed" : "pending",
            "cmid" => (int) $module["id"],
            "instance" => (int) ($module["instance"] ?? 0),
            "url" => $module["url"] ?? null,
        ];
    }

    return ["ok" => true, "data" => ["faculty" => $faculty, "services" => $services]];
}

/* ------------------------------------------------------------------ */
/* Assignments page                                                     */
/* ------------------------------------------------------------------ */

function moodle_assignment_feed(bool $fresh = false): array
{
    $user = moodle_resolve_user_id();
    if (!$user["ok"]) return $user;
    $userId = $user["data"];

    if (!$fresh) {
        $cached = moodle_cache_get("feed:$userId");
        if ($cached !== null) return ["ok" => true, "data" => $cached];
    }

    // Everything here is read AS the student (their own sign-in token). The shared
    // site token belongs to a non-admin account, and Moodle silently hides the
    // student's courses from it, which is why no assignments were showing.
    $token = auth_user_token();
    $courses = moodle_user_json("core_enrol_get_users_courses", ["userid" => $userId]);
    if (!$courses["ok"]) return $courses;

    $visible = [];
    foreach ($courses["data"] as $course) {
        if (
            (isset($course["visible"]) && !$course["visible"])
            || (isset($course["uservisible"]) && !$course["uservisible"])
        ) {
            continue;
        }
        $visible[(int) $course["id"]] = $course;
    }

    // 1) contents + completion of every course, all at once
    $contents = $visible ? moodle_student_contents_multi($userId, array_keys($visible)) : [];
    // A course that cannot be read is skipped so it cannot hide the others;
    // the error is only reported when none of the courses could be read.
    $readable = [];
    $firstError = null;
    foreach ($visible as $id => $course) {
        if (!empty($contents[$id]["ok"])) {
            $readable[$id] = $course;
        } elseif ($firstError === null) {
            $firstError = $contents[$id] ?? ["ok" => false, "message" => "Course contents could not be read."];
        }
    }
    if ($visible && !$readable && $firstError !== null) return $firstError;
    $visible = $readable;

    $calls = [];
    foreach ($visible as $id => $course) {
        $calls[$id] = [
            "function" => "core_completion_get_activities_completion_status",
            "params" => ["courseid" => $id, "userid" => $userId],
            "token" => $token,
        ];
    }
    $completionByCourse = [];
    foreach (moodle_get_json_multi($calls) as $id => $result) {
        $completionByCourse[$id] = moodle_parse_completed($result);
    }

    $activityTypes = [
        "assign",
        "quiz",
        "lesson",
        "forum",
        "choice",
        "workshop",
        "scorm",
        "database",
        "glossary",
        "wiki",
        "h5pactivity",
    ];

    // 2) pick the activities to show; remember which need a submission check
    $items = [];
    $checks = [];
    foreach ($visible as $courseId => $course) {
        foreach ($contents[$courseId]["data"] as $section) {
            foreach ($section["modules"] ?? [] as $module) {
                $type = strtolower($module["modname"] ?? "activity");
                $moduleName = trim(strtolower(strip_tags($module["name"] ?? "")));
                $isAnnouncement = $type === "forum" && (
                    $moduleName === "announcements"
                    || $moduleName === "announcement"
                    || str_contains($moduleName, "site news")
                    || str_contains($moduleName, "course news")
                );
                if (
                    !in_array($type, $activityTypes, true)
                    || $isAnnouncement
                    || (isset($module["visible"]) && !$module["visible"])
                    || (isset($module["uservisible"]) && !$module["uservisible"])
                ) {
                    continue;
                }
                $dueTs = null;
                $closedAt = null;
                foreach ($module["dates"] ?? [] as $date) {
                    $label = strtolower($date["label"] ?? "");
                    if (isset($date["timestamp"]) && (stripos($label, "close") !== false || stripos($label, "end") !== false)) {
                        $closedAt = (int) $date["timestamp"];
                    }
                    if (isset($date["timestamp"]) && stripos($label, "due") !== false) {
                        $dueTs = (int) $date["timestamp"];
                    }
                }

                $cmid = (int) $module["id"];
                $doneAt = null;
                $isDone = isset($completionByCourse[$courseId][$cmid]);
                if ($isDone) {
                    $doneAt = $completionByCourse[$courseId][$cmid] ?: null;
                } else {
                    $call = moodle_submission_call($type, $module, $userId);
                    if ($call) {
                        $call["token"] = $token;
                        $checks["m$cmid"] = ["type" => $type, "call" => $call];
                    }
                }

                $items["m$cmid"] = compact("course", "module", "type", "dueTs", "closedAt", "isDone", "doneAt");
            }
        }
    }

    // 3) all "did the student hand this in?" checks at once
    $calls = [];
    foreach ($checks as $key => $check) $calls[$key] = $check["call"];
    foreach (moodle_get_json_multi($calls) as $key => $result) {
        $ts = moodle_submission_time_from($checks[$key]["type"], $result);
        if ($ts !== null) {
            $items[$key]["isDone"] = true;
            $items[$key]["doneAt"] = $ts ?: null;
        }
    }

    // 3b) points: the gradebook gives the maximum (and the student's score, when
    // shown), the assignment / quiz / lesson lists fill in what it leaves out.
    $needs = [];
    foreach ($items as $item) {
        $needs[(int) $item["course"]["id"]][$item["type"]] = true;
    }
    $calls = [];
    foreach ($needs as $courseId => $types) {
        $calls["grades:$courseId"] = [
            "function" => "gradereport_user_get_grade_items",
            "params" => ["courseid" => $courseId, "userid" => $userId],
            "token" => $token,
        ];
        if (!empty($types["assign"])) {
            $calls["assign:$courseId"] = ["function" => "mod_assign_get_assignments", "params" => ["courseids" => [$courseId]], "token" => $token];
        }
        if (!empty($types["quiz"])) {
            $calls["quiz:$courseId"] = ["function" => "mod_quiz_get_quizzes_by_courses", "params" => ["courseids" => [$courseId]], "token" => $token];
        }
        if (!empty($types["lesson"])) {
            $calls["lesson:$courseId"] = ["function" => "mod_lesson_get_lessons_by_courses", "params" => ["courseids" => [$courseId]], "token" => $token];
        }
    }
    $pointData = moodle_get_json_multi($calls);

    $maxByCm = [];   // cmid => maximum points
    $scoreByCm = []; // cmid => the student's score
    foreach ($pointData as $key => $result) {
        if (!$result["ok"]) continue;
        [$kind] = explode(":", $key, 2);
        if ($kind === "grades") {
            foreach ($result["data"]["usergrades"][0]["gradeitems"] ?? [] as $gi) {
                $cm = (int) ($gi["cmid"] ?? 0);
                $max = (float) ($gi["grademax"] ?? 0);
                if ($cm && ($gi["itemtype"] ?? "") === "mod" && $max > 0) {
                    $maxByCm[$cm] = $max;
                    if (isset($gi["graderaw"]) && is_numeric($gi["graderaw"])) {
                        $scoreByCm[$cm] = (float) $gi["graderaw"];
                    }
                }
            }
        } elseif ($kind === "assign") {
            foreach ($result["data"]["courses"][0]["assignments"] ?? [] as $a) {
                $cm = (int) ($a["cmid"] ?? 0);
                if ($cm && (float) ($a["grade"] ?? 0) > 0) $maxByCm[$cm] = $maxByCm[$cm] ?? (float) $a["grade"];
            }
        } elseif ($kind === "quiz") {
            foreach ($result["data"]["quizzes"] ?? [] as $q) {
                $cm = (int) ($q["coursemodule"] ?? 0);
                if ($cm && (float) ($q["grade"] ?? 0) > 0) $maxByCm[$cm] = $maxByCm[$cm] ?? (float) $q["grade"];
            }
        } elseif ($kind === "lesson") {
            foreach ($result["data"]["lessons"] ?? [] as $l) {
                $cm = (int) ($l["coursemodule"] ?? 0);
                if ($cm && (float) ($l["grade"] ?? 0) > 0) $maxByCm[$cm] = $maxByCm[$cm] ?? (float) $l["grade"];
            }
        }
    }
    $fmtPoints = fn(float $n): string => rtrim(rtrim(number_format($n, 2, ".", ""), "0"), ".");

    // 4) build the list
    $now = time();
    $assignments = [];
    foreach ($items as $item) {
        // Hide closed activities, but keep ones the student completed.
        if (!$item["isDone"] && $item["closedAt"] !== null && $item["closedAt"] < $now) {
            continue;
        }
        if ($item["isDone"]) {
            $status = "submitted";
        } elseif ($item["dueTs"] !== null && $item["dueTs"] < $now) {
            $status = "late";
        } else {
            $status = "open";
        }
        $module = $item["module"];
        $course = $item["course"];
        $cm = (int) $module["id"];
        $points = null;
        if (isset($maxByCm[$cm])) {
            $points = $fmtPoints($maxByCm[$cm]);
            if (isset($scoreByCm[$cm])) {
                $points = $fmtPoints($scoreByCm[$cm]) . " / " . $points;
            }
        }
        $assignments[] = [
            "id" => $module["id"],
            "course" => $course["shortname"] ?? $course["fullname"],
            "courseTitle" => $course["fullname"],
            "title" => trim(strip_tags($module["name"] ?? ucfirst($item["type"]))),
            "description" => trim(strip_tags($module["description"] ?? "")) ?: "LMS activity",
            "due" => $item["dueTs"] !== null ? moodle_format_time($item["dueTs"]) : null,
            "points" => $points,
            "status" => $status,
            "submittedAt" => $item["doneAt"] ? moodle_format_time($item["doneAt"]) : null,
            "activityType" => ucfirst($item["type"]),
            "url" => $module["url"] ?? null,
        ];
    }

    moodle_cache_set("feed:$userId", $assignments, MOODLE_FEED_TTL);
    return ["ok" => true, "data" => $assignments];
}
