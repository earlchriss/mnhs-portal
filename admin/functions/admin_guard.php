<?php
// Extra check for pages that can create or delete real accounts. Call before any output.
//
// Access is controlled by the Moodle sign-in: admin/includes/guard.php runs
// auth_require_admin(), which only lets Moodle site administrators through.
// So there is no second password prompt. This function only makes sure that
// guard really ran first, and refuses the page if someone forgot to include it.

function admin_require(): void
{
    // guard.php loads functions/auth.php and calls auth_require_admin() before this point.
    if (function_exists("auth_require_admin")) return;

    http_response_code(403);
    echo "This admin page is locked: the Moodle administrator sign-in did not run. Include includes/guard.php at the top of the page.";
    exit;
}
