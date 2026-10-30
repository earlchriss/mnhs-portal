<?php
require_once __DIR__ . "/../functions/moodle.php";

// Already signed in? Go straight to the portal (must happen before any output).
if (auth_user()) {
    header("Location: " . auth_safe_next($_GET["next"] ?? null));
    exit;
}

// The page embeds a one-time token tied to the session, so never cache it.
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

include __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/react.php";

react_mount("login", [
    "schoolName" => $schoolName,
    "logo" => "/assets/img/banner.png",
    "csrfToken" => auth_csrf_token(),
    "next" => auth_safe_next($_GET["next"] ?? null),
    "endpoint" => "/login/authenticate.php",
    "forgotUrl" => moodle_site_base() . "/login/forgot_password.php",
], "min-vh-100 bg-body-tertiary");

include __DIR__ . "/../includes/footer.php";
