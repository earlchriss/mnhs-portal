<?php
// Admins only: signs in through Moodle and starts the session (replaces session_start()).
require_once __DIR__ . "/includes/guard.php";
require_once __DIR__ . "/functions/admin_guard.php";
admin_require(); // before anything else: this page can create and delete real accounts
require_once __DIR__ . "/functions/users.php";
require_once __DIR__ . "/../includes/react.php";

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$errors = [];
$importResult = $_SESSION["import_result"] ?? null; // shown once, then forgotten
unset($_SESSION["import_result"]);
$old = ["action" => "add", "id" => 0, "username" => "", "firstname" => "", "lastname" => "", "email" => "", "idnumber" => "", "role" => "student"];

$moodle = moodle_users_ready();

/* ---------- Handle POST before any output so we can redirect ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);

    if (!hash_equals($_SESSION["csrf"], (string) ($_POST["csrf"] ?? ""))) {
        $errors[] = "Your session expired. Please try again.";
    } elseif (!in_array($action, ["add", "edit", "delete", "import"], true)) {
        $errors[] = "Unknown action.";
    } elseif (!$moodle["ok"]) {
        $errors[] = $moodle["message"];
        $errors[] = "Nothing was changed.";
    } elseif ($action === "import") {
        $file = $_FILES["csv"] ?? null;
        if (!$file || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Choose a CSV file to import.";
        } elseif ($file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
            $errors[] = $file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE
                ? "That file is too large. The limit is 1 MB." : "The file could not be uploaded. Try again.";
        } elseif ($file["size"] > IMPORT_MAX_BYTES) {
            $errors[] = "That file is too large. The limit is 1 MB.";
        } else {
            $run = users_import((string) file_get_contents($file["tmp_name"]));
            if ($run["ok"]) {
                $r = $run["result"];
                $_SESSION["import_result"] = $r;
                $_SESSION["flash"] = [
                    $r["failed"] ? "warning" : "success",
                    "Import finished: {$r["created"]} created" . ($r["failed"] ? ", {$r["failed"]} not created." : ".")
                ];
                header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
                exit;
            }
            $errors[] = $run["error"];
        }
    } else {
        $result = match ($action) {
            "add" => users_create($_POST),
            "edit" => users_update($id, $_POST),
            "delete" => users_delete($id),
        };
        if ($result["ok"]) {
            $_SESSION["flash"] = ["success", $result["message"]];
            header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
            exit;
        }
        $errors = $result["errors"];
    }

    $old["id"] = $id; // lets a failed delete reopen its confirmation
    if ($errors && $action === "import") $old["action"] = "import";
    if ($errors && !in_array($action, ["delete", "import"], true)) {
        // Hand the typed values back (never the passwords) so the form reopens filled in.
        $old = ["action" => $action === "edit" ? "edit" : "add", "id" => $id] + array_map(
            fn($v) => is_string($v) ? $v : "",
            array_intersect_key($_POST, array_flip(["username", "firstname", "lastname", "email", "idnumber", "role"]))
        ) + $old;
    }
}

if ($importResult) { // generated passwords are in the page: never cache it
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
}
$users = array_map("users_public", users_load());
usort($users, fn($a, $b) => strcasecmp($a["lastname"] . $a["firstname"], $b["lastname"] . $b["firstname"]));

include "includes/header.php";
include "includes/navbar.php";

react_mount("admin-users", [
    "users" => $users,
    "roles" => USER_ROLES,
    "moodleReady" => $moodle["ok"],
    "moodleMessage" => $moodle["message"],
    "flash" => $flash,
    "errors" => $errors,
    "deleteError" => ($_POST["action"] ?? "") === "delete" ? $errors : [],
    "old" => $old,
    "importResult" => $importResult,
    "importMax" => IMPORT_MAX_ROWS,
    "csrf" => $_SESSION["csrf"],
]);
react_assets();
?>
<script>
    // If React never took over the page (page not registered in src/main.jsx, or the
    // bundle was not rebuilt), say so instead of leaving an empty screen.
    window.addEventListener("load", function() {
        setTimeout(function() {
            var root = document.getElementById("root");
            if (root && !root.children.length) {
                root.innerHTML = '<div class="container-xl"><div class="alert alert-warning" role="alert">' +
                    '<strong>The Users page did not start.</strong> Make sure <code>AdminUsers</code> is registered as ' +
                    '<code>"admin-users"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1000);
    });
</script>
<?php
include "includes/footer.php";
exit;
