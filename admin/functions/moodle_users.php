<?php
// Moodle account management for the admin Users page.
//
// Every call is sent as a POST, so passwords and the web-service token never
// appear in a URL or in the web server's access log.
//
// Needs, on the Moodle web-service token used by MOODLE_WSTOKEN:
//   core_user_create_users, core_user_update_users, core_user_delete_users
// and the token's user must hold the capabilities moodle/user:create,
// moodle/user:update and moodle/user:delete.

/*
 * ---------------------------------------------------------------------------
 * MOODLE SETTINGS: the one place to put them when the host has no env vars.
 * A real server environment variable with the same name always wins.
 *
 *   MOODLE_WSTOKEN   the web-service token from Moodle
 *                    (Site administration > Server > Web services > Manage tokens)
 *   MOODLE_REST_URL  the Moodle REST address
 *   MOODLE_CA_BUNDLE optional path to a CA certificate file, only if curl
 *                    reports a certificate error
 *
 * Do not commit the real token to a public repository.
 * ---------------------------------------------------------------------------
 */
const MOODLE_LOCAL_SETTINGS = [
    "MOODLE_WSTOKEN"   => "e29e7c805ffd59bbce0a42530c68045b",   // <-- paste your token between the quotes
    "MOODLE_REST_URL"  => "https://lms.mabolonationalhs.me/webservice/rest/server.php",
    "MOODLE_CA_BUNDLE" => "",
];

/** A Moodle setting: the environment variable if set, otherwise the value above. */
function moodle_setting(string $name): string
{
    $value = getenv($name);
    if ($value !== false && $value !== "") return (string) $value;
    return trim((string) (MOODLE_LOCAL_SETTINGS[$name] ?? ""));
}

/** Is the server set up to talk to Moodle? ["ok" => bool, "message" => string] */
function moodle_users_ready(): array
{
    if (!function_exists("curl_init")) {
        return ["ok" => false, "message" => "The PHP curl extension is not enabled, so accounts cannot be created in LMS."];
    }
    if (moodle_setting("MOODLE_WSTOKEN") === "") {
        return ["ok" => false, "message" => "MOODLE_WSTOKEN is not set. Paste the token into MOODLE_LOCAL_SETTINGS in admin/functions/moodle_users.php (or set it as a server environment variable), so accounts can be created in LMS."];
    }
    return ["ok" => true, "message" => ""];
}

/**
 * Call one Moodle web-service function over POST.
 * Returns ["ok" => true, "data" => mixed] or ["ok" => false, "message" => string, "code" => string].
 */
function moodle_users_call(string $function, array $params): array
{
    $ready = moodle_users_ready();
    if (!$ready["ok"]) return ["ok" => false, "message" => $ready["message"], "code" => "notready"];

    $baseUrl = moodle_setting("MOODLE_REST_URL") ?: "https://lms.mabolonationalhs.me/webservice/rest/server.php";
    $body = http_build_query(array_merge($params, [
        "wstoken" => moodle_setting("MOODLE_WSTOKEN"),
        "wsfunction" => $function,
        "moodlewsrestformat" => "json",
    ]));

    $request = curl_init($baseUrl);
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false, // a redirect would re-send nothing useful and could leak the token
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_HTTPHEADER => ["Accept: application/json"],
    ];
    $caBundle = moodle_setting("MOODLE_CA_BUNDLE") ?: "C:\\xampp\\apache\\bin\\curl-ca-bundle.crt";
    if (is_file($caBundle)) $options[CURLOPT_CAINFO] = $caBundle;
    curl_setopt_array($request, $options);
    $raw = curl_exec($request);
    $error = curl_error($request);
    $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);

    if ($raw === false || $error) {
        return ["ok" => false, "message" => "LMS could not be reached. Nothing was changed.", "code" => "unreachable"];
    }

    $raw = trim($raw);
    // update and delete answer with an empty body or the JSON literal null.
    if ($raw === "" || $raw === "null") {
        return $status >= 400
            ? ["ok" => false, "message" => "LMS returned HTTP {$status}.", "code" => "http"]
            : ["ok" => true, "data" => null];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ["ok" => false, "message" => "LMS returned an invalid response (HTTP {$status}).", "code" => "badresponse"];
    }
    if (isset($data["exception"]) || isset($data["errorcode"])) {
        $code = (string) ($data["errorcode"] ?? "exception");
        $message = (string) ($data["message"] ?? "LMS rejected the request.");
        if ($code === "invalidtoken") {
            $message = "The LMS web-service token is invalid or expired. Update MOODLE_WSTOKEN in admin/functions/moodle_users.php.";
        } elseif ($code === "accessexception" || stripos($message, "access control") !== false) {
            $message = "LMS refused {$function}. Add it to the web-service the token belongs to, and make sure the token's user may manage accounts.";
        } elseif ($code === "invalidparameter" || $code === "invalidparameterexception") {
            // e.g. "Invalid parameter value detected (Username already exists: jdoe)"
            $message = trim(preg_replace('/^Invalid parameter value detected\s*\(?|\)\s*$/i', "", $message)) ?: $message;
        }
        return ["ok" => false, "message" => $message, "code" => $code];
    }
    return ["ok" => true, "data" => $data];
}

/** Create the account in Moodle. Returns ["ok" => true, "id" => int] or an error array. */
function moodle_user_create(array $user, string $password): array
{
    $record = [
        "username" => $user["username"],
        "password" => $password,
        "firstname" => $user["firstname"],
        "lastname" => $user["lastname"],
        "email" => $user["email"],
        "auth" => "manual",
        "lang" => "en",
        "timezone" => "Asia/Manila",
    ];
    if (($user["idnumber"] ?? "") !== "") $record["idnumber"] = $user["idnumber"];

    $result = moodle_users_call("core_user_create_users", ["users" => [$record]]);
    if (!$result["ok"]) return $result;
    $id = (int) ($result["data"][0]["id"] ?? 0);
    if ($id <= 0) {
        return ["ok" => false, "message" => "LMS did not return the new account's ID.", "code" => "noid"];
    }
    return ["ok" => true, "id" => $id];
}

/** Update an existing Moodle account. A blank $password leaves the password alone. */
function moodle_user_update(int $moodleId, array $user, string $password = ""): array
{
    $record = [
        "id" => $moodleId,
        "username" => $user["username"],
        "firstname" => $user["firstname"],
        "lastname" => $user["lastname"],
        "email" => $user["email"],
        "idnumber" => (string) ($user["idnumber"] ?? ""),
    ];
    if ($password !== "") $record["password"] = $password;

    $result = moodle_users_call("core_user_update_users", ["users" => [$record]]);
    return $result["ok"] ? ["ok" => true] : $result;
}

/**
 * Delete the account in Moodle. An account that is already gone from Moodle
 * counts as success, so the project record can still be removed.
 */
function moodle_user_delete(int $moodleId): array
{
    $result = moodle_users_call("core_user_delete_users", ["userids" => [$moodleId]]);
    if ($result["ok"]) return ["ok" => true, "already_gone" => false];

    $gone = in_array($result["code"] ?? "", ["invalidrecord", "dml_missing_record_exception"], true)
        || stripos($result["message"], "can't find data record") !== false;
    if ($gone) return ["ok" => true, "already_gone" => true];
    return $result;
}
