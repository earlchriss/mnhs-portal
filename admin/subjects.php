<?php
session_start();
require_once __DIR__ . "/../includes/react.php";
require_once __DIR__ . "/functions/subjects.php";

/* NOTE: includes/header.php loads functions/function.php, which defines its own
   $subjects, $term, $today ... so this page uses different variable names. */

$page_term = "1st Semester, AY 2026-2027";

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));

$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$errors = [];
$old = ["action" => "add", "id" => 0, "code" => "", "title" => "", "description" => ""];

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = ($_POST["action"] ?? "") === "edit" ? "edit" : "add";
    $edit_id = $action === "edit" ? (int) ($_POST["id"] ?? 0) : 0;

    $old = [
        "action"      => $action,
        "id"          => $edit_id,
        "code"        => "",
        "title"       => (string) ($_POST["title"] ?? ""),
        "description" => (string) ($_POST["description"] ?? ""),
    ];

    if (!hash_equals($_SESSION["csrf"], (string) ($_POST["csrf"] ?? ""))) {
        $errors[] = "Your session expired. Please try again.";
    } else {
        $rows = subjects_all();
        $position = null;
        foreach ($rows as $i => $row) {
            if ($row["id"] === $edit_id) $position = $i;
        }
        if ($position !== null) $old["code"] = $rows[$position]["code"];

        if ($action === "edit" && $position === null) {
            $errors[] = "That subject no longer exists.";
        } else {
            [$errors, $clean] = subject_validate($_POST);
        }

        if (!$errors) {
            if ($action === "edit") {
                // The code is fixed once created: only title and description change
                $rows[$position] = ["id" => $edit_id, "code" => $rows[$position]["code"]] + $clean;
                $message = "Subject " . $rows[$position]["code"] . " updated.";
            } else {
                $next_id = $rows ? max(array_column($rows, "id")) + 1 : 1;
                $code = subject_generate_code($rows);
                $rows[] = ["id" => $next_id, "code" => $code] + $clean;
                $message = "Subject " . $code . " added.";
            }
            subjects_save($rows);
            $_SESSION["flash"] = ["success", $message];
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }
}

/* ---------- Data for the page ---------- */
// Which sections each subject is scheduled in (known once the schedule page has been opened)
$schedule_known = isset($_SESSION["schedules"]) && is_array($_SESSION["schedules"]);
$used_in = [];
foreach ($_SESSION["schedules"] ?? [] as $entry) {
    $used_in[$entry["subject_id"]][] = $entry["section"];
}

$subject_rows = [];
foreach (subjects_all() as $row) {
    $row["sections"] = array_values(array_unique($used_in[$row["id"]] ?? []));
    $subject_rows[] = $row;
}

include "includes/header.php";
include "includes/navbar.php";

react_mount("admin-subjects", [
    "term" => $page_term,
    "subjects" => $subject_rows,
    "scheduleKnown" => $schedule_known,
    "flash" => $flash,
    "errors" => $errors,
    "old" => $old,
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
                    '<strong>The Subjects page did not start.</strong> Make sure <code>AdminSubjects</code> is registered as ' +
                    '<code>"admin-subjects"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
