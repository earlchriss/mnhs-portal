<?php
// Project accounts, each linked to its Moodle account by moodle_id.
//
// Records live in admin/data/users.data.php. The file starts with a line that
// stops PHP, so the JSON below it can never be downloaded from the web.
// TODO: replace users_load()/users_save() with database queries later; the
// create/update/delete functions below do not need to change.
// Passwords are sent to Moodle and never stored here.

require_once __DIR__ . "/moodle_users.php";

const USER_ROLES = ["student" => "Student", "teacher" => "Teacher", "admin" => "Admin"];
const USERS_FILE_HEADER = "<?php http_response_code(404); exit; ?>\n";

function users_dir(): string
{
    return dirname(__DIR__) . "/data";
}

function users_file(): string
{
    return users_dir() . "/users.data.php";
}

function users_load(): array
{
    $path = users_file();
    if (!is_file($path)) return [];
    $raw = (string) file_get_contents($path);
    if (str_starts_with($raw, USERS_FILE_HEADER)) $raw = substr($raw, strlen(USERS_FILE_HEADER));
    $data = json_decode($raw, true);
    return is_array($data) ? array_values($data) : [];
}

function users_save(array $users): bool
{
    if (!is_dir(users_dir()) && !@mkdir(users_dir(), 0775, true) && !is_dir(users_dir())) return false;
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    // Write to a temp file, then rename, so a crash never leaves half a file.
    $tmp = users_file() . "." . bin2hex(random_bytes(4)) . ".tmp";
    if (file_put_contents($tmp, USERS_FILE_HEADER . $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, users_file())) {
        $ok = @copy($tmp, users_file()); // Windows can refuse to rename over an open file
        @unlink($tmp);
        return $ok;
    }
    return true;
}

/** Run $work(array $users) with an exclusive lock, so two admins cannot overwrite each other. */
function users_locked(callable $work)
{
    if (!is_dir(users_dir())) @mkdir(users_dir(), 0775, true);
    $lock = fopen(users_dir() . "/users.lock", "c");
    if (!$lock) return $work(users_load());
    flock($lock, LOCK_EX);
    try {
        return $work(users_load());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** What the browser may see: never anything secret. */
function users_public(array $user): array
{
    return [
        "id" => (int) $user["id"],
        "moodleId" => $user["moodle_id"] ?? null,
        "username" => $user["username"],
        "firstname" => $user["firstname"],
        "lastname" => $user["lastname"],
        "email" => $user["email"],
        "idnumber" => $user["idnumber"] ?? "",
        "role" => $user["role"],
        "created" => $user["created"] ?? "",
    ];
}

/** Clean and check form input. Returns [cleanUser, errors]. */
function users_validate(array $in, array $users, ?int $editingId): array
{
    $clean = [
        "username" => strtolower(trim((string) ($in["username"] ?? ""))),
        "firstname" => trim((string) ($in["firstname"] ?? "")),
        "lastname" => trim((string) ($in["lastname"] ?? "")),
        "email" => trim((string) ($in["email"] ?? "")),
        "idnumber" => trim((string) ($in["idnumber"] ?? "")),
        "role" => (string) ($in["role"] ?? ""),
    ];
    $password = (string) ($in["password"] ?? "");
    $confirm = (string) ($in["password_confirm"] ?? "");
    $errors = [];

    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/', $clean["username"])) {
        $errors[] = "Username must be 3 to 50 characters: lowercase letters, numbers, dot, dash or underscore.";
    }
    if ($clean["firstname"] === "" || mb_strlen($clean["firstname"]) > 60) $errors[] = "First name is required (up to 60 characters).";
    if ($clean["lastname"] === "" || mb_strlen($clean["lastname"]) > 60) $errors[] = "Last name is required (up to 60 characters).";
    if (!filter_var($clean["email"], FILTER_VALIDATE_EMAIL) || mb_strlen($clean["email"]) > 100) $errors[] = "Enter a valid email address.";
    if (mb_strlen($clean["idnumber"]) > 50) $errors[] = "ID number is too long (up to 50 characters).";
    if (!isset(USER_ROLES[$clean["role"]])) $errors[] = "Choose a role.";

    $creating = $editingId === null;
    if ($creating && $password === "") {
        $errors[] = "A password is required.";
    } elseif ($password !== "") {
        if (strlen($password) < 8) $errors[] = "The password must be at least 8 characters.";
        if ($password !== $confirm) $errors[] = "The two passwords do not match.";
    }

    foreach ($users as $other) {
        if ($editingId !== null && (int) $other["id"] === $editingId) continue;
        if (strcasecmp($other["username"], $clean["username"]) === 0) $errors[] = "That username is already taken.";
        if (strcasecmp($other["email"], $clean["email"]) === 0) $errors[] = "That email is already used by another account.";
        if ($clean["idnumber"] !== "" && ($other["idnumber"] ?? "") === $clean["idnumber"]) $errors[] = "That ID number belongs to another account.";
    }
    return [$clean, array_values(array_unique($errors)), $password];
}

/** Create the project account and the matching Moodle account. */
function users_create(array $in): array
{
    return users_locked(function (array $users) use ($in) {
        [$clean, $errors, $password] = users_validate($in, $users, null);
        if ($errors) return ["ok" => false, "errors" => $errors];

        $moodle = moodle_user_create($clean, $password);
        if (!$moodle["ok"]) {
            return ["ok" => false, "errors" => ["LMS: " . $moodle["message"], "The account was not created."]];
        }

        $next = $users ? max(array_column($users, "id")) + 1 : 1;
        $record = ["id" => $next, "moodle_id" => $moodle["id"]] + $clean + [
            "created" => (new DateTime("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d H:i"),
        ];
        $users[] = $record;
        if (!users_save($users)) {
            // Never leave a Moodle account that the project does not know about.
            $undo = moodle_user_delete($moodle["id"]);
            $note = $undo["ok"]
                ? "The LMS account was removed again."
                : "Remove the LMS account '{$clean["username"]}' by hand.";
            return ["ok" => false, "errors" => ["The project could not save the account (is the admin/data folder writable?).", $note]];
        }
        return ["ok" => true, "message" => "Account created for {$clean["firstname"]} {$clean["lastname"]} and added to LMS.", "user" => $record];
    });
}

/** Update the project account and the Moodle account together. */
function users_update(int $id, array $in): array
{
    return users_locked(function (array $users) use ($id, $in) {
        $index = null;
        foreach ($users as $i => $u) if ((int) $u["id"] === $id) $index = $i;
        if ($index === null) return ["ok" => false, "errors" => ["That account no longer exists."]];

        [$clean, $errors, $password] = users_validate($in, $users, $id);
        if ($errors) return ["ok" => false, "errors" => $errors];

        $existing = $users[$index];
        if (empty($existing["moodle_id"])) {
            return ["ok" => false, "errors" => ["This account is not linked to LMS, so it cannot be edited here."]];
        }
        $moodle = moodle_user_update((int) $existing["moodle_id"], $clean, $password);
        if (!$moodle["ok"]) {
            return ["ok" => false, "errors" => ["LMS: " . $moodle["message"], "Nothing was changed."]];
        }

        $users[$index] = $clean + $existing;
        if (!users_save($users)) {
            return ["ok" => false, "errors" => ["LMS was updated, but the project could not save the change (is the admin/data folder writable?). Try again."]];
        }
        return ["ok" => true, "message" => "Saved changes for {$clean["firstname"]} {$clean["lastname"]}."];
    });
}

/** Delete the Moodle account first, then the project account. */
function users_delete(int $id): array
{
    return users_locked(function (array $users) use ($id) {
        $index = null;
        foreach ($users as $i => $u) if ((int) $u["id"] === $id) $index = $i;
        if ($index === null) return ["ok" => false, "errors" => ["That account no longer exists."]];

        $existing = $users[$index];
        $note = "";
        if (!empty($existing["moodle_id"])) {
            $moodle = moodle_user_delete((int) $existing["moodle_id"]);
            if (!$moodle["ok"]) {
                return ["ok" => false, "errors" => ["LMS: " . $moodle["message"], "The account was not deleted."]];
            }
            if (!empty($moodle["already_gone"])) $note = " It had already been removed from LMS.";
        }
        array_splice($users, $index, 1);
        if (!users_save($users)) {
            return ["ok" => false, "errors" => ["The LMS account was deleted, but the project could not save the change (is the admin/data folder writable?). Try deleting again."]];
        }
        return ["ok" => true, "message" => "Deleted {$existing["firstname"]} {$existing["lastname"]} from the project and LMS." . $note];
    });
}

/* ======================= Bulk import (CSV) ======================= */

const IMPORT_MAX_ROWS = 200;
const IMPORT_MAX_BYTES = 1048576; // 1 MB

/** A random password that meets Moodle's default policy (upper, lower, digit, symbol). */
function users_generate_password(int $length = 12): string
{
    $sets = ["ABCDEFGHJKLMNPQRSTUVWXYZ", "abcdefghijkmnopqrstuvwxyz", "23456789", "!@#$%*?-_"];
    $chars = [];
    foreach ($sets as $set) $chars[] = $set[random_int(0, strlen($set) - 1)];
    $all = implode("", $sets);
    while (count($chars) < $length) $chars[] = $all[random_int(0, strlen($all) - 1)];
    for ($i = count($chars) - 1; $i > 0; $i--) { // Fisher-Yates with a secure random source
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode("", $chars);
}

/**
 * Read CSV text into rows. Returns ["error" => string] or ["rows" => [[line, fields], ...]].
 * Needs a header row. Accepts comma, semicolon or tab separators (Excel varies by region).
 */
function users_import_parse(string $text): array
{
    $text = preg_replace('/^\xEF\xBB\xBF/', "", $text); // Excel's byte-order mark
    if (!mb_check_encoding($text, "UTF-8")) return ["error" => "The file is not UTF-8 text. In Excel, use Save As, then CSV UTF-8."];
    if (trim($text) === "") return ["error" => "The file is empty."];

    $firstLine = strtok($text, "\r\n") ?: "";
    $delimiter = ",";
    $best = substr_count($firstLine, ",");
    foreach ([";" => ";", "\t" => "\t"] as $d) {
        if (substr_count($firstLine, $d) > $best) {
            $best = substr_count($firstLine, $d);
            $delimiter = $d;
        }
    }

    $aliases = [
        "firstname" => ["firstname", "first name", "first_name", "given name"],
        "lastname" => ["lastname", "last name", "last_name", "surname", "family name"],
        "username" => ["username", "user name", "user_name", "login"],
        "email" => ["email", "e-mail", "email address"],
        "role" => ["role", "type"],
        "idnumber" => ["idnumber", "id number", "id_number", "id", "lrn", "student id"],
        "password" => ["password", "temporary password"],
    ];

    $stream = fopen("php://memory", "r+");
    fwrite($stream, $text);
    rewind($stream);

    $header = fgetcsv($stream, 0, $delimiter, '"', "");
    $map = [];
    foreach ((array) $header as $i => $name) {
        $name = strtolower(trim((string) $name));
        foreach ($aliases as $field => $names) {
            if (in_array($name, $names, true) && !isset($map[$field])) $map[$field] = $i;
        }
    }
    $missing = array_diff(["username", "firstname", "lastname", "email"], array_keys($map));
    if ($missing) {
        fclose($stream);
        return ["error" => "The first row must be a header with these columns: username, firstname, lastname, email. Missing: " . implode(", ", $missing) . "."];
    }

    $rows = [];
    $line = 1;
    while (($fields = fgetcsv($stream, 0, $delimiter, '"', "")) !== false) {
        $line++;
        if ($fields === [null] || !array_filter($fields, fn($v) => trim((string) $v) !== "")) continue; // blank line
        if (count($rows) >= IMPORT_MAX_ROWS) {
            fclose($stream);
            return ["error" => "Too many rows. Import up to " . IMPORT_MAX_ROWS . " users at a time."];
        }
        $row = [];
        foreach ($map as $field => $i) $row[$field] = trim((string) ($fields[$i] ?? ""));
        $rows[] = [$line, $row];
    }
    fclose($stream);
    if (!$rows) return ["error" => "The file has a header but no users."];
    return ["rows" => $rows];
}

/**
 * Create every row in Moodle and the project, one by one, so a bad row never
 * blocks the good ones. Returns ["ok" => true, "result" => [...]] or ["ok" => false, "error" => string].
 */
function users_import(string $text): array
{
    $parsed = users_import_parse($text);
    if (isset($parsed["error"])) return ["ok" => false, "error" => $parsed["error"]];

    @set_time_limit(300);
    $out = [];
    $created = 0;
    foreach ($parsed["rows"] as [$line, $row]) {
        $generated = ($row["password"] ?? "") === "";
        $password = $generated ? users_generate_password() : $row["password"];
        $in = $row + ["role" => ""];
        if (trim($in["role"]) === "") $in["role"] = "student";
        $in["role"] = strtolower(trim($in["role"]));
        $in["password"] = $password;
        $in["password_confirm"] = $password;

        $result = users_create($in);
        $name = trim(($row["firstname"] ?? "") . " " . ($row["lastname"] ?? ""));
        if ($result["ok"]) {
            $created++;
            $out[] = [
                "line" => $line,
                "username" => strtolower($row["username"]),
                "name" => $name,
                "status" => "created",
                "message" => "Created",
                "password" => $generated ? $password : ""
            ];
        } else {
            $out[] = [
                "line" => $line,
                "username" => $row["username"],
                "name" => $name,
                "status" => "failed",
                "message" => implode(" ", array_map(fn($e) => rtrim($e, ".") . ".", $result["errors"])),
                "password" => ""
            ];
        }
    }
    return ["ok" => true, "result" => [
        "total" => count($out),
        "created" => $created,
        "failed" => count($out) - $created,
        "rows" => $out,
    ]];
}
