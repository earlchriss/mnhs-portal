<?php
// POST only (with the CSRF token from the navbar), so another site cannot log people out.
require_once __DIR__ . "/../functions/auth.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && auth_check_csrf((string) ($_POST["csrf"] ?? ""))) {
    auth_sign_out();
}
header("Location: /login/");
exit;
