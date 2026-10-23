<?php
require_once __DIR__ . "/functions/auth.php";

auth_sign_out();
header("Location: /login/");
exit;
