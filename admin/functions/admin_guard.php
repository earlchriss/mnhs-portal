<?php
// Protects pages that can create or delete real accounts. Call before any output.
//
// Production: set PORTAL_ADMIN_USER and PORTAL_ADMIN_PASSWORD on the server
// (the same way you set MOODLE_WSTOKEN). The browser then asks for them.
// Not set: only requests from this computer (localhost) are allowed, so the
// page is never open to the network by accident.

function admin_require(): void
{
    $user = getenv("PORTAL_ADMIN_USER");
    $pass = getenv("PORTAL_ADMIN_PASSWORD");

    if ($user && $pass) {
        $givenUser = (string) ($_SERVER["PHP_AUTH_USER"] ?? "");
        $givenPass = (string) ($_SERVER["PHP_AUTH_PW"] ?? "");
        // Compare both always, so timing does not reveal which one was wrong.
        $okUser = hash_equals($user, $givenUser);
        $okPass = hash_equals($pass, $givenPass);
        if ($okUser && $okPass) return;

        header('WWW-Authenticate: Basic realm="Admin Portal", charset="UTF-8"');
        http_response_code(401);
        echo "Sign in to use the admin portal.";
        exit;
    }

    $addr = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
    if (in_array($addr, ["127.0.0.1", "::1"], true)) return;

    http_response_code(403);
    echo "The Users page is locked. Set PORTAL_ADMIN_USER and PORTAL_ADMIN_PASSWORD on the server to open it from other computers.";
    exit;
}
