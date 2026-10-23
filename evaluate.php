<?php
require_once __DIR__ . "/functions/moodle.php";
auth_require_login(); // must run before any output

include "includes/header.php";
include "includes/navbar.php";

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

/* ---------- Moodle feedback helpers ---------- */

/** Options of a multiple-choice item: [index => ["num" => shown number, "label" => text]]. */
function eval_choice_options(string $presentation, bool $rated): array
{
    $parts = explode(">>>>>", $presentation, 2);
    $subtype = strtolower(substr(trim($parts[0] ?? "r"), 0, 1));
    $body = explode("<<<<<", $parts[1] ?? "")[0];
    $options = [];
    foreach (explode("|", $body) as $i => $line) {
        $num = (string) ($i + 1);
        $label = $line;
        if ($rated && str_contains($line, "####")) {
            [$value, $label] = explode("####", $line, 2);
            $num = trim($value) !== "" ? trim($value) : $num;
        }
        $label = trim(strip_tags($label));
        $options[$i + 1] = ["num" => $num, "label" => $label !== "" ? $label : $num];
    }
    return ["subtype" => $subtype, "options" => $options];
}

/** Load every page of the feedback as the signed-in student. */
function eval_load_pages(int $feedbackId): array
{
    $access = moodle_user_json("mod_feedback_get_feedback_access_information", ["feedbackid" => $feedbackId]);
    if (!$access["ok"]) return $access;
    $info = $access["data"];
    if (!empty($info["isalreadysubmitted"])) {
        return ["ok" => true, "done" => true];
    }
    if (array_key_exists("isopen", $info) && !$info["isopen"]) {
        return ["ok" => false, "message" => "This evaluation is not open right now."];
    }
    if (array_key_exists("cancomplete", $info) && !$info["cancomplete"]) {
        return ["ok" => false, "message" => "You are not allowed to answer this evaluation."];
    }

    $launch = moodle_user_json("mod_feedback_launch_feedback", ["feedbackid" => $feedbackId]);
    if (!$launch["ok"]) return $launch;

    $pages = [];
    for ($page = 0; $page < 20; $page++) {
        $result = moodle_user_json("mod_feedback_get_page_items", ["feedbackid" => $feedbackId, "page" => $page]);
        if (!$result["ok"]) return $result;
        $pages[$page] = $result["data"]["items"] ?? [];
        if (empty($result["data"]["hasnextpage"])) break;
    }
    return ["ok" => true, "pages" => $pages];
}

/**
 * Turn Moodle items into what the page shows:
 *  - sections: rating questions grouped under Moodle "label" items (headings)
 *  - extras: text / number questions, shown under "Comments and suggestions"
 *  - inputs: every answerable question, for validation and submission
 * unsupported = true if a question type or condition can't be shown here.
 */
function eval_build(array $pages): array
{
    $sections = [];
    $current = ["title" => "", "items" => []];
    $extras = [];
    $inputs = [];
    $unsupported = false;

    foreach ($pages as $pageNo => $items) {
        foreach ($items as $item) {
            $typ = (string) ($item["typ"] ?? "");
            if (!empty($item["dependitem"])) {
                $unsupported = true; // conditional questions need Moodle's own form
                continue;
            }
            $name = trim(preg_replace('/\s+/', " ", strip_tags((string) ($item["name"] ?? ""))));

            if ($typ === "label") {
                $title = $name !== "" ? $name : trim(preg_replace('/\s+/', " ", strip_tags((string) ($item["presentation"] ?? ""))));
                if ($current["items"]) {
                    $sections[] = $current;
                }
                $current = ["title" => mb_substr($title, 0, 120), "items" => []];
                continue;
            }
            if ($typ === "info" || $typ === "pagebreak") {
                continue;
            }

            $base = [
                "key" => $typ . "_" . $item["id"],
                "page" => $pageNo,
                "label" => $name,
                "required" => !empty($item["required"]),
            ];

            if ($typ === "multichoice" || $typ === "multichoicerated") {
                $choice = eval_choice_options((string) ($item["presentation"] ?? ""), $typ === "multichoicerated");
                if (!in_array($choice["subtype"], ["r", "d"], true) || !$choice["options"]) {
                    $unsupported = true; // checkbox lists are not supported here
                    continue;
                }
                $entry = $base + ["kind" => "rating", "options" => $choice["options"]];
                $current["items"][] = $entry;
                $inputs[] = $entry;
            } elseif ($typ === "textarea" || $typ === "textfield") {
                $entry = $base + ["kind" => "text", "long" => $typ === "textarea"];
                $extras[] = $entry;
                $inputs[] = $entry;
            } elseif ($typ === "numeric") {
                $entry = $base + ["kind" => "number"];
                $extras[] = $entry;
                $inputs[] = $entry;
            } else {
                $unsupported = true; // e.g. captcha
            }
        }
    }
    if ($current["items"]) {
        $sections[] = $current;
    }
    return ["sections" => $sections, "extras" => $extras, "inputs" => $inputs, "unsupported" => $unsupported];
}

/* ---------- Student and form ---------- */
$user = auth_user();
$student = [
    "name"    => $user["name"],
    "id"      => $user["username"],
    "grade"   => "3rd Year",
    "section" => "IT-3A",
];
$term = "1st Semester, AY 2026-2027";

$type = $_GET["type"] ?? "";
$id   = (string) ($_GET["id"] ?? "");
$form = null;
$load_error = "";

// Only forms that belong to this student are ever accepted.
$evals = moodle_evaluation_forms();
if (!$evals["ok"]) {
    $load_error = $evals["message"];
} else {
    $pool = $type === "faculty" ? $evals["data"]["faculty"] : ($type === "service" ? $evals["data"]["services"] : []);
    foreach ($pool as $candidate) {
        if ((string) $candidate["id"] === $id) {
            $form = $candidate;
            break;
        }
    }
}

$form_title = $form_subject = $form_detail = $form_icon = "";
if ($form && $type === "faculty") {
    $form_title = "Faculty evaluation";
    $form_subject = $form["code"] . (!empty($form["course"]) ? " – " . $form["course"] : "");
    $form_detail = "Instructor: " . $form["instructor"];
    $form_icon = "fa-chalkboard-user";
} elseif ($form) {
    $form_title = "Office and service evaluation";
    $form_subject = $form["title"];
    $form_icon = "fa-building";
}

/* ---------- Load the Moodle questions ---------- */
$already_done = $form && ($form["status"] ?? "") === "completed";
$built = ["sections" => [], "extras" => [], "inputs" => [], "unsupported" => false];
$feedbackId = $form ? (int) ($form["instance"] ?? 0) : 0;
$pages = [];

if ($form && !$already_done && !$load_error) {
    if (!$feedbackId) {
        $load_error = "Please go back to the home page and open this evaluation again.";
    } else {
        $loaded = eval_load_pages($feedbackId);
        if (!$loaded["ok"]) {
            $load_error = $loaded["message"];
        } elseif (!empty($loaded["done"])) {
            $already_done = true;
        } else {
            $pages = $loaded["pages"];
            $built = eval_build($pages);
        }
    }
}

/* ---------- Handle submission ---------- */
$errors = [];
$submitted = false;
$submit_error = "";
$values = [];

$can_answer = $form && !$already_done && !$load_error && !$built["unsupported"];

if ($can_answer && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!auth_check_csrf($_POST["csrf"] ?? null)) {
        $submit_error = "Your session expired. Please reload the page and try again.";
    } else {
        $answers = is_array($_POST["a"] ?? null) ? $_POST["a"] : [];
        foreach ($built["inputs"] as $input) {
            $raw = isset($answers[$input["key"]]) && is_string($answers[$input["key"]]) ? trim($answers[$input["key"]]) : "";
            $values[$input["key"]] = $raw;

            if ($raw === "") {
                if ($input["required"]) $errors[$input["key"]] = true;
            } elseif ($input["kind"] === "rating" && !isset($input["options"][(int) $raw])) {
                $errors[$input["key"]] = true;
            } elseif ($input["kind"] === "number" && !is_numeric($raw)) {
                $errors[$input["key"]] = true;
            } elseif ($input["kind"] === "text" && strlen($raw) > 2000) {
                $errors[$input["key"]] = true;
            }
        }

        if (!$errors) {
            $completed = false;
            foreach ($pages as $pageNo => $_items) {
                $params = ["feedbackid" => $feedbackId, "page" => $pageNo, "goprevious" => 0];
                $n = 0;
                foreach ($built["inputs"] as $input) {
                    if ($input["page"] === $pageNo && $values[$input["key"]] !== "") {
                        $params["responses"][$n++] = ["name" => $input["key"], "value" => $values[$input["key"]]];
                    }
                }
                $result = moodle_user_json("mod_feedback_process_page", $params);
                if (!$result["ok"]) {
                    $submit_error = $result["message"];
                    break;
                }
                $completed = !empty($result["data"]["completed"]);
            }
            if (!$submit_error) {
                if ($completed) {
                    $submitted = true;
                    unset($_SESSION["eval_cache"]); // so the home page shows it as completed
                } else {
                    $submit_error = "LMS did not accept the evaluation. Please check your answers and try again.";
                }
            }
        }
    }
}

$rating_inputs = array_values(array_filter($built["inputs"], fn($i) => $i["kind"] === "rating"));
$total_q = count($rating_inputs);
$answered = 0;
foreach ($rating_inputs as $input) {
    if (($values[$input["key"]] ?? "") !== "") $answered++;
}
$percent = $total_q ? round($answered / $total_q * 100) : 0;
$legend = $rating_inputs ? $rating_inputs[0]["options"] : [];
?>
<main class="page-content home bg-body-tertiary min-vh-100">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">

                <a href="/" class="small text-decoration-none d-inline-block mb-3">
                    <i class="fas fa-arrow-left me-1"></i>Back to evaluation forms
                </a>

                <?php if ($load_error): ?>
                    <!-- Moodle problem -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <span class="fs-1 text-warning"><i class="fas fa-triangle-exclamation"></i></span>
                            <h1 class="h5 fw-bold mt-3">Evaluation unavailable</h1>
                            <p class="text-body-secondary mb-4"><?= h($load_error) ?></p>
                            <a href="/" class="btn btn-primary">Go to evaluation forms</a>
                        </div>
                    </div>

                <?php elseif (!$form): ?>
                    <!-- Invalid link -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <span class="fs-1 text-warning"><i class="fas fa-triangle-exclamation"></i></span>
                            <h1 class="h5 fw-bold mt-3">Form not found</h1>
                            <p class="text-body-secondary mb-4">This evaluation form does not exist or the link is incorrect.</p>
                            <a href="/" class="btn btn-primary">Go to evaluation forms</a>
                        </div>
                    </div>

                <?php elseif ($already_done): ?>
                    <!-- Already submitted -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <span class="fs-1 text-success"><i class="fas fa-circle-check"></i></span>
                            <h1 class="h5 fw-bold mt-3">Already submitted</h1>
                            <p class="text-body-secondary mb-4">You have already completed the evaluation for <span class="fw-semibold"><?= h($form_subject) ?></span>.</p>
                            <a href="/" class="btn btn-primary">Back to evaluation forms</a>
                        </div>
                    </div>

                <?php elseif ($submitted): ?>
                    <!-- Success -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <span class="fs-1 text-success"><i class="fas fa-circle-check"></i></span>
                            <h1 class="h4 fw-bold mt-3">Thank you!</h1>
                            <p class="text-body-secondary mb-1">Your evaluation for</p>
                            <p class="fw-semibold mb-3"><?= h($form_subject) ?></p>
                            <p class="text-body-secondary mb-4">has been submitted.</p>
                            <a href="/" class="btn btn-primary">Back to evaluation forms</a>
                        </div>
                    </div>

                <?php elseif ($built["unsupported"]): ?>
                    <!-- Question types that can't be shown here -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <span class="fs-1 text-warning"><i class="fas fa-up-right-from-square"></i></span>
                            <h1 class="h5 fw-bold mt-3">Open this evaluation in LMS</h1>
                            <p class="text-body-secondary mb-4">This form uses question types that can't be shown in the portal.</p>
                            <?php if (!empty($form["url"])): ?>
                                <a href="<?= h($form["url"]) ?>" target="_blank" rel="noreferrer" class="btn btn-primary">Open in LMS</a>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- Banner -->
                    <section class="welcome bg-primary text-white rounded-4 p-4 mb-4">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-white bg-opacity-25 fs-4 flex-shrink-0" style="width:56px;height:56px">
                                    <i class="fas <?= h($form_icon) ?>"></i>
                                </span>
                                <div>
                                    <p class="small opacity-75 mb-1"><?= h($form_title) ?></p>
                                    <h1 class="h4 fw-bold mb-0"><?= h($form_subject) ?></h1>
                                    <?php if ($form_detail): ?>
                                        <p class="mb-0 opacity-75"><?= h($form_detail) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge rounded-pill text-bg-light text-primary fw-semibold px-3 py-2"><?= h($term) ?></span>
                        </div>

                        <hr class="border-white opacity-25 my-3">

                        <div class="row g-2 small">
                            <div class="col-12 col-md-6">
                                <span class="opacity-75">Student:</span>
                                <span class="fw-semibold"><?= h($student["name"]) ?> (<?= h($student["id"]) ?>)</span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="opacity-75">Grade:</span>
                                <span class="fw-semibold"><?= h($student["grade"]) ?></span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="opacity-75">Section:</span>
                                <span class="fw-semibold"><?= h($student["section"]) ?></span>
                            </div>
                        </div>
                    </section>

                    <?php if ($submit_error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                            <i class="fas fa-circle-exclamation"></i>
                            <div><?= h($submit_error) ?></div>
                        </div>
                    <?php elseif ($errors): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                            <i class="fas fa-circle-exclamation"></i>
                            <div>Please answer every required question. The ones you missed are outlined in red.</div>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="evalForm" novalidate>
                        <input type="hidden" name="csrf" value="<?= h(auth_csrf_token()) ?>">

                        <!-- Progress + scale (sticks under the navbar) -->
                        <div class="card border-0 shadow-sm mb-4 sticky-top" style="top:68px;z-index:1010">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="fw-semibold">Progress</span>
                                    <span class="text-body-secondary"><span id="answeredCount"><?= $answered ?></span> of <?= $total_q ?> answered</span>
                                </div>
                                <div class="progress mb-3" style="height:8px" role="progressbar" aria-label="Evaluation progress" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" id="progressBar" style="width:<?= $percent ?>%"></div>
                                </div>
                                <?php if ($legend && count($legend) <= 7): ?>
                                    <div class="d-flex flex-wrap gap-1 gap-md-2">
                                        <?php foreach ($legend as $opt): ?>
                                            <span class="badge bg-primary-subtle text-primary-emphasis fw-normal"><?= h($opt["num"]) ?> = <?= h($opt["label"]) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!$built["inputs"]): ?>
                            <div class="alert alert-info">This evaluation has no questions yet.</div>
                        <?php endif; ?>

                        <!-- Questions -->
                        <?php $num = 0; ?>
                        <?php foreach ($built["sections"] as $section): ?>
                            <section class="mb-4">
                                <?php if ($section["title"] !== ""): ?>
                                    <h2 class="h5 fw-bold mb-3"><?= h($section["title"]) ?></h2>
                                <?php endif; ?>
                                <div class="card border-0 shadow-sm">
                                    <div class="list-group list-group-flush rounded-3">
                                        <?php foreach ($section["items"] as $item): $num++;
                                            $key = $item["key"]; ?>
                                            <div class="list-group-item p-3 p-md-4 q-item <?= isset($errors[$key]) ? "border border-danger" : "" ?>">
                                                <div class="d-flex gap-3 mb-3">
                                                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis align-self-start"><?= $num ?></span>
                                                    <div>
                                                        <div class="fw-semibold" id="q-<?= h($key) ?>">
                                                            <?= h($item["label"]) ?>
                                                            <?php if ($item["required"]): ?><span class="text-danger" title="Required">*</span><?php endif; ?>
                                                        </div>
                                                        <div class="small text-danger q-error <?= isset($errors[$key]) ? "" : "d-none" ?>">Please select a rating.</div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2" role="radiogroup" aria-labelledby="q-<?= h($key) ?>">
                                                    <?php foreach ($item["options"] as $idx => $opt): $rid = "r-$key-$idx"; ?>
                                                        <input type="radio" class="btn-check" name="a[<?= h($key) ?>]" id="<?= h($rid) ?>" value="<?= $idx ?>"
                                                            <?= (($values[$key] ?? "") === (string) $idx) ? "checked" : "" ?>>
                                                        <label class="btn btn-outline-primary flex-fill py-2 lh-sm" for="<?= h($rid) ?>" title="<?= h($opt["label"]) ?>">
                                                            <span class="d-block fw-bold"><?= h($opt["num"]) ?></span>
                                                            <span class="d-none d-md-block small fw-normal"><?= h($opt["label"]) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <!-- Comments and other written answers -->
                        <?php if ($built["extras"]): ?>
                            <section class="mb-4">
                                <h2 class="h5 fw-bold mb-3">Comments and suggestions</h2>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <?php foreach ($built["extras"] as $k => $item): $key = $item["key"]; ?>
                                            <div class="<?= $k ? "mt-4" : "" ?>">
                                                <?php if ($item["label"] !== ""): ?>
                                                    <label for="f-<?= h($key) ?>" class="form-label fw-semibold">
                                                        <?= h($item["label"]) ?>
                                                        <?php if ($item["required"]): ?><span class="text-danger" title="Required">*</span><?php endif; ?>
                                                    </label>
                                                <?php endif; ?>
                                                <?php if ($item["kind"] === "number"): ?>
                                                    <input type="number" step="any" id="f-<?= h($key) ?>" name="a[<?= h($key) ?>]"
                                                        class="form-control <?= isset($errors[$key]) ? "is-invalid" : "" ?>"
                                                        value="<?= h($values[$key] ?? "") ?>">
                                                <?php elseif ($item["long"]): ?>
                                                    <textarea id="f-<?= h($key) ?>" name="a[<?= h($key) ?>]" rows="4" maxlength="2000" data-counter="c-<?= h($key) ?>"
                                                        class="form-control <?= isset($errors[$key]) ? "is-invalid" : "" ?>"
                                                        placeholder="Share what went well and what can be improved."><?= h($values[$key] ?? "") ?></textarea>
                                                    <div class="form-text d-flex justify-content-between">
                                                        <span><i class="fas fa-lock me-1"></i>Your answers are confidential.</span>
                                                        <span><span id="c-<?= h($key) ?>"><?= strlen($values[$key] ?? "") ?></span>/2000</span>
                                                    </div>
                                                <?php else: ?>
                                                    <input type="text" id="f-<?= h($key) ?>" name="a[<?= h($key) ?>]" maxlength="2000"
                                                        class="form-control <?= isset($errors[$key]) ? "is-invalid" : "" ?>"
                                                        value="<?= h($values[$key] ?? "") ?>">
                                                <?php endif; ?>
                                                <?php if (isset($errors[$key])): ?>
                                                    <div class="small text-danger mt-1">Please check this answer.</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 mb-5">
                            <a href="/" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-paper-plane me-1"></i>Submit evaluation
                            </button>
                        </div>
                    </form>

                    <script>
                        (function() {
                            const form = document.getElementById('evalForm');
                            const total = <?= (int) $total_q ?>;
                            const bar = document.getElementById('progressBar');
                            const count = document.getElementById('answeredCount');

                            function update() {
                                const names = new Set(
                                    [...form.querySelectorAll('input[type=radio]:checked')].map(i => i.name)
                                );
                                const pct = total ? Math.round(names.size / total * 100) : 0;
                                count.textContent = names.size;
                                bar.style.width = pct + '%';
                                bar.parentElement.setAttribute('aria-valuenow', pct);
                            }

                            form.addEventListener('change', function(e) {
                                if (e.target.type === 'radio') {
                                    const item = e.target.closest('.q-item');
                                    item.classList.remove('border', 'border-danger');
                                    item.querySelector('.q-error').classList.add('d-none');
                                    update();
                                }
                            });
                            form.querySelectorAll('textarea[data-counter]').forEach(function(box) {
                                const out = document.getElementById(box.dataset.counter);
                                box.addEventListener('input', () => out.textContent = box.value.length);
                            });
                            update();
                        })();
                    </script>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php" ?>