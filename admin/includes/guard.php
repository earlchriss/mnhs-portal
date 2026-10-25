<?php
// Put this at the very top of every admin page (before any output, before handling a POST):
//     require_once __DIR__ . "/includes/guard.php";
// It signs the person in through the same Moodle login as the student portal and only
// lets Moodle site administrators through.
require_once __DIR__ . "/../../functions/auth.php";
auth_require_admin();
