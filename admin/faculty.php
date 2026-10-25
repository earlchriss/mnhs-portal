<?php
// Admins only: signs in through Moodle and starts the session (replaces session_start()).
require_once __DIR__ . "/includes/guard.php";
require_once __DIR__ . "/functions/admin_guard.php";
admin_require(); // ratings and faculty records are admin-only
require_once __DIR__ . "/functions/faculty.php";
require_once __DIR__ . "/functions/schedules.php"; // Moodle teachers for the "Linked LMS teacher" dropdown
require_once __DIR__ . "/functions/moodle_feedback.php"; // ratings from Moodle Feedback activities
require_once __DIR__ . "/../includes/react.php";

$page_term = "1st Semester, AY 2026-2027";

$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$sync_flash = $_SESSION["sync_flash"] ?? null; // result of the last "Sync ratings from Moodle"
unset($_SESSION["sync_flash"]);
$errors = [];
$old = [
    "action" => "add",
    "id" => 0,
    "employee_no" => "",
    "first_name" => "",
    "last_name" => "",
    "email" => "",
    "department" => "",
    "job_title" => "",
    "status" => "active",
    "teacher_id" => "",
];

/* ---------- Load from the database ---------- */
$faculty = [];
$teachers = [];
$sections = [];
$terms = [$page_term];
$last_sync = null;
$activities = []; // Feedback activities from the last sync, for the "Add question" modal
$load_error = null; // shown as a banner on the page

try {
    $faculty = faculty_all();
    $teachers = schedule_teachers();
    $sections = faculty_eval_sections(); // Moodle course names that have ratings
    $terms = array_values(array_unique(array_merge([$page_term], faculty_eval_terms())));
    $last_sync = faculty_last_sync();
    try {
        $activities = faculty_feedback_activities();
    } catch (Throwable $e) {
        error_log("Feedback list failed: " . $e->getMessage()); // usually: the cm_id column has not been added yet
    }
} catch (Throwable $e) {
    error_log("Faculty load failed: " . $e->getMessage());
    $load_error = "Could not load the faculty from the database: " . $e->getMessage();
}

/* ---------- Handle POST (before any output so we can redirect) ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string) ($_POST["action"] ?? "");
    $id = (int) ($_POST["id"] ?? 0);

    if ($load_error) {
        $errors[] = "The faculty could not be loaded, so nothing was saved.";
    } elseif (!hash_equals($_SESSION["csrf"], (string) ($_POST["csrf"] ?? ""))) {
        $errors[] = "Your session expired. Please try again.";
    } elseif (!in_array($action, ["add", "edit", "delete", "sync"], true)) {
        $errors[] = "Unknown action.";
    } elseif ($action === "sync") {
        // Always redirects: the result is shown in the banner, not in the add/edit form
        try {
            $_SESSION["sync_flash"] = feedback_sync_notice(feedback_sync($page_term));
        } catch (Throwable $e) {
            error_log("Ratings sync failed: " . $e->getMessage());
            $_SESSION["sync_flash"] = ["danger", "Could not sync the ratings: " . $e->getMessage()];
        }
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    } elseif ($action === "delete") {
        try {
            $member = faculty_find($id);
            if (!$member) {
                $errors[] = "That faculty member no longer exists.";
            } else {
                faculty_delete($id);
                $_SESSION["flash"] = ["success", $member["name"] . " was deleted."];
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            }
        } catch (Throwable $e) {
            error_log("Faculty delete failed: " . $e->getMessage());
            $errors[] = "Could not delete the faculty member: " . $e->getMessage();
        }
    } else {
        $existing = $action === "edit" ? faculty_find($id) : null;
        if ($action === "edit" && !$existing) {
            $errors[] = "That faculty member no longer exists.";
        } else {
            try {
                [$errors, $clean] = faculty_validate($_POST, $action === "edit" ? $id : 0);
                if (!$errors) {
                    if ($action === "edit") {
                        faculty_update($id, $clean);
                        $message = $clean["first_name"] . " " . $clean["last_name"] . " updated.";
                    } else {
                        faculty_create($clean);
                        $message = $clean["first_name"] . " " . $clean["last_name"] . " added.";
                    }
                    $_SESSION["flash"] = ["success", $message];
                    header("Location: " . $_SERVER["REQUEST_URI"]);
                    exit;
                }
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = "The employee number or the linked LMS teacher is already used by another faculty member.";
                } else {
                    error_log("Faculty save failed: " . $e->getMessage());
                    $errors[] = "Could not save the faculty member: " . $e->getMessage();
                }
            } catch (Throwable $e) {
                error_log("Faculty save failed: " . $e->getMessage());
                $errors[] = "Could not save the faculty member: " . $e->getMessage();
            }
        }
    }

    $old["id"] = $id; // lets a failed delete reopen its confirmation
    if ($errors && $action !== "delete") {
        // Hand the typed values back so the form reopens filled in.
        $old = ["action" => $action === "edit" ? "edit" : "add", "id" => $id] + array_map(
            fn($v) => is_string($v) ? $v : "",
            array_intersect_key($_POST, array_flip(["employee_no", "first_name", "last_name", "email", "department", "job_title", "status", "teacher_id"]))
        ) + $old;
    }
}

/* ---------- Ratings (filtered by term / section from the URL) ---------- */
$filter_term = (string) ($_GET["term"] ?? "");
$filter_section = (string) ($_GET["section"] ?? "");
if ($filter_term !== "" && !in_array($filter_term, $terms, true)) $filter_term = "";
if ($filter_section !== "" && !in_array($filter_section, $sections, true)) $filter_section = "";

$ratings = [];
if (!$load_error) {
    try {
        $ratings = faculty_ratings($filter_term, $filter_section);
    } catch (Throwable $e) {
        error_log("Faculty ratings failed: " . $e->getMessage());
        $load_error = "Could not load the ratings from the database: " . $e->getMessage();
    }
}

// Keep our data safe: included files must not overwrite these variables
$_page_data = compact("faculty", "teachers", "sections", "terms", "ratings", "load_error", "sync_flash", "last_sync", "page_term", "activities");
include "includes/header.php";
include "includes/navbar.php";
extract($_page_data);
unset($_page_data);

if ($load_error) {
    echo '<div class="container-xl mt-3"><div class="alert alert-danger" role="alert">'
        . '<div class="fw-semibold">Something went wrong</div>'
        . '<div class="small">' . htmlspecialchars($load_error, ENT_QUOTES, "UTF-8") . '</div>'
        . '</div></div>';
}

/* ---------- Moodle ratings bar: sync result + "Sync ratings from Moodle" ---------- */
$h = fn(string $text) => htmlspecialchars($text, ENT_QUOTES, "UTF-8");
echo '<div class="container-xl mt-3">';
if ($sync_flash) {
    $icon = $sync_flash[0] === "success" ? "fa-circle-check" : "fa-triangle-exclamation";
    echo '<div class="alert alert-' . $h($sync_flash[0]) . ' d-flex align-items-start gap-2" role="status">'
        . '<i class="fas ' . $icon . ' mt-1" aria-hidden="true"></i><div>' . $h($sync_flash[1]) . '</div></div>';
}
echo '<form method="post" class="d-flex flex-wrap align-items-center justify-content-end gap-2"'
    . ' onsubmit="var b=this.querySelector(\'.js-sync\');b.disabled=true;b.lastChild.textContent=\' Syncing...\';">'
    . '<input type="hidden" name="csrf" value="' . $h($_SESSION["csrf"]) . '">'
    . '<input type="hidden" name="action" value="sync">'
    . '<span class="small text-body-secondary">Ratings come from LMS Feedback activities. '
    . ($last_sync ? 'Last synced ' . $h(date("M j, Y g:i A", strtotime($last_sync))) . '.' : 'Not synced yet.')
    . '</span>'
    . '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal"><i class="fas fa-plus" aria-hidden="true"></i> Add question</button>'
    . '<button type="submit" class="btn btn-outline-primary btn-sm js-sync"><i class="fas fa-rotate" aria-hidden="true"></i><span> Sync ratings from Moodle</span></button>'
    . '</form></div>';

/* ---------- "Add question" modal: questions live in Moodle, so this opens the Moodle editor ---------- */
echo '<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionTitle" aria-hidden="true">'
    . '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow">'
    . '<div class="modal-header"><h2 class="modal-title h5 fw-bold" id="addQuestionTitle">Add a question</h2>'
    . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
    . '<div class="modal-body">'
    . '<p class="small text-body-secondary">Students answer inside Moodle, so questions are added there. Choose a Feedback activity to open its editor in a new tab.</p>';
if ($activities) {
    echo '<div class="list-group mb-3">';
    foreach ($activities as $activity) {
        echo '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3" href="'
            . $h(feedback_editor_url($activity)) . '" target="_blank" rel="noopener">'
            . '<span><span class="fw-semibold d-block">' . $h($activity["name"]) . '</span>'
            . '<span class="small text-body-secondary">' . $h($activity["section"]) . '</span></span>'
            . '<span class="text-nowrap small text-primary">' . ($activity["cm_id"] ? "Open editor" : "Open course")
            . ' <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a>';
    }
    echo '</div>';
} else {
    echo '<div class="alert alert-info small">No Feedback activities found yet. In Moodle, open a course, turn editing on, '
        . 'choose <strong>Add an activity or resource &rarr; Feedback</strong>, then come back and click <strong>Sync ratings from Moodle</strong>.</div>'
        . '<a class="btn btn-outline-primary btn-sm mb-3" href="' . $h(feedback_moodle_base()) . '" target="_blank" rel="noopener">Open Moodle</a>';
}
echo '<div class="small text-body-secondary"><div class="fw-semibold mb-1">In the Moodle editor</div>'
    . '<ol class="ps-3 mb-0"><li>Choose <strong>Add question &rarr; Multiple choice (rated)</strong>.</li>'
    . '<li>Type the choices one per line, each as <code>value####label</code>, for example <code>1####Poor</code> up to <code>5####Excellent</code>.</li>'
    . '<li>Save, then click <strong>Sync ratings from Moodle</strong> here. Only rated questions count toward the average.</li></ol></div>'
    . '</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>'
    . '</div></div></div>';

react_mount("admin-faculty", [
    "term" => $page_term,
    "faculty" => $faculty,
    "ratings" => $ratings,
    "terms" => $terms,
    "sections" => $sections,
    "teachers" => $teachers,
    "statuses" => FACULTY_STATUSES,
    "filters" => ["term" => $filter_term, "section" => $filter_section],
    "flash" => $flash,
    "errors" => $errors,
    "deleteError" => ($_POST["action"] ?? "") === "delete" ? $errors : [],
    "old" => $old,
    "csrf" => $_SESSION["csrf"],
]);
react_assets();
?>
<script>
    // If React never took over the page (page not registered in src/main.jsx, or the
    // bundle was not rebuilt), say so instead of leaving an empty screen.
    var pageErrors = [];
    window.addEventListener("error", function(event) {
        pageErrors.push(event.message || "Unknown script error");
    });
    window.addEventListener("unhandledrejection", function(event) {
        pageErrors.push(String(event.reason && event.reason.message || event.reason));
    });

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
    window.addEventListener("load", function() {
        setTimeout(function() {
            var root = document.getElementById("root");
            if (root && !root.children.length) {
                var details = pageErrors.length ?
                    '<div class="mt-2"><strong>Error:</strong> <code>' + escapeHtml(pageErrors.join(" | ")) + '</code></div>' : '';
                root.innerHTML = '<div class="container-xl"><div class="alert alert-warning" role="alert">' +
                    '<strong>The Faculty page did not start.</strong> Make sure <code>AdminFaculty</code> is registered as ' +
                    '<code>"admin-faculty"</code> in <code>src/main.jsx</code>, run <code>npm run build</code>, ' +
                    'then hard refresh (Ctrl+F5).' + details + '</div></div>';
            }
        }, 1500);
    });
</script>
<?php
include "includes/footer.php";
exit;
