<?php
session_start();

$default_settings = [
    "school_name" => "Mabolo National High School",
    "term" => "1st Semester, AY 2026-2027",
    "eval_open" => "2026-10-01",
    "eval_deadline" => "2026-11-15",
    "homepage_title" => "Welcome to the Student Portal",
    "homepage_message" => "Access your classes, grades, schedule and campus services in one place.",
    "announcement_title" => "Midterm grades are now posted",
    "about_description" => "Mabolo National High School provides accessible, student-centered education for every learner.",
    "about_address" => "Mabolo, Cebu City",
    "about_email" => "info@mabolo.edu.ph",
    "about_phone" => "+63 32 123 4567",
    "admission_title" => "Admissions are open",
    "admission_message" => "Start your application for the coming school year.",
    "admission_requirements" => "Completed application form\nReport card or transcript\nBirth certificate",
    "admission_deadline" => "2027-03-31",
];

if (!isset($_SESSION["site_settings"])) {
    $_SESSION["site_settings"] = $default_settings;
}
$_SESSION["site_settings"] = array_merge($default_settings, $_SESSION["site_settings"]);
$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$errors = [];
$settings = $_SESSION["site_settings"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $errors[] = "Your session expired. Please try again.";
    } else {
        $settings = [];
        foreach ($default_settings as $key => $value) {
            $settings[$key] = trim($_POST[$key] ?? "");
        }
        if ($settings["school_name"] === "") $errors[] = "School name is required.";
        if ($settings["term"] === "") $errors[] = "Term is required.";
        if (!$settings["eval_open"] || !strtotime($settings["eval_open"])) $errors[] = "Choose a valid evaluation open date.";
        if (!$settings["eval_deadline"] || !strtotime($settings["eval_deadline"])) $errors[] = "Choose a valid evaluation deadline.";
        if ($settings["admission_deadline"] && !strtotime($settings["admission_deadline"])) $errors[] = "Choose a valid admission deadline.";
        if (!$errors && strtotime($settings["eval_deadline"]) < strtotime($settings["eval_open"])) $errors[] = "The evaluation deadline must be on or after the open date.";

        if (!$errors) {
            $_SESSION["site_settings"] = $settings;
            $_SESSION["flash"] = ["success", "Site settings saved."];
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }
}

include "includes/header.php";
include "includes/navbar.php";
?>
<main class="page-content home bg-body-tertiary min-vh-100">
    <div class="container-xl">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-0">Site Settings</h1>
                <p class="text-body-secondary mb-0">Configure the portal, school information and admissions.</p>
            </div>
        </div>
        <div class="alert alert-info small d-flex align-items-start gap-2" role="note"><i class="fas fa-circle-info mt-1"></i>
            <div>These settings use session data for testing. Changes are not saved to a database yet.</div>
        </div>
        <?php if ($flash): ?><div class="alert alert-<?= htmlspecialchars($flash[0]) ?> d-flex align-items-center gap-2" role="status"><i class="fas fa-circle-check"></i><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger small" role="alert">
                <div class="fw-semibold mb-1"><i class="fas fa-circle-exclamation me-1"></i>Could not save settings</div>
                <ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
            </div><?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
            <ul class="nav nav-tabs settings-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general-settings" type="button" role="tab"><i class="fas fa-sliders me-1"></i>General Settings</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#homepage-settings" type="button" role="tab"><i class="fas fa-house me-1"></i>Homepage Settings</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#about-settings" type="button" role="tab"><i class="fas fa-circle-info me-1"></i>About Settings</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#admission-settings" type="button" role="tab"><i class="fas fa-user-plus me-1"></i>Admission Settings</button></li>
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active" id="general-settings" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12 col-xl-9">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h2 class="h5 fw-bold mb-3">General Settings</h2>
                                    <div class="mb-3"><label for="school_name" class="form-label fw-semibold">School name</label><input type="text" class="form-control" id="school_name" name="school_name" value="<?= htmlspecialchars($settings["school_name"]) ?>" required></div>
                                    <div class="mb-3"><label for="term" class="form-label fw-semibold">Current term</label><input type="text" class="form-control" id="term" name="term" value="<?= htmlspecialchars($settings["term"]) ?>" required></div>
                                    <div class="row g-2">
                                        <div class="col-md-6"><label for="eval_open" class="form-label fw-semibold">Evaluation opens</label><input type="text" class="form-control js-date-picker" id="eval_open" name="eval_open" autocomplete="off" inputmode="numeric" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($settings["eval_open"]) ?>" required></div>
                                        <div class="col-md-6"><label for="eval_deadline" class="form-label fw-semibold">Evaluation closes</label><input type="text" class="form-control js-date-picker" id="eval_deadline" name="eval_deadline" autocomplete="off" inputmode="numeric" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($settings["eval_deadline"]) ?>" required></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-pane fade" id="homepage-settings" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12 col-xl-9">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h2 class="h5 fw-bold mb-3">Homepage Settings</h2>
                                    <div class="mb-3"><label for="homepage_title" class="form-label fw-semibold">Welcome title</label><input type="text" class="form-control" id="homepage_title" name="homepage_title" value="<?= htmlspecialchars($settings["homepage_title"]) ?>"></div>
                                    <div class="mb-3"><label for="homepage_message" class="form-label fw-semibold">Welcome message</label><textarea class="form-control" id="homepage_message" name="homepage_message" rows="3"><?= htmlspecialchars($settings["homepage_message"]) ?></textarea></div>
                                    <div><label for="announcement_title" class="form-label fw-semibold">Featured announcement</label><input type="text" class="form-control" id="announcement_title" name="announcement_title" value="<?= htmlspecialchars($settings["announcement_title"]) ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-pane fade" id="about-settings" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12 col-xl-9">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h2 class="h5 fw-bold mb-3">About Settings</h2>
                                    <div class="mb-3"><label for="about_description" class="form-label fw-semibold">About the school</label><textarea class="form-control" id="about_description" name="about_description" rows="4"><?= htmlspecialchars($settings["about_description"]) ?></textarea></div>
                                    <div class="mb-3"><label for="about_address" class="form-label fw-semibold">Address</label><input type="text" class="form-control" id="about_address" name="about_address" value="<?= htmlspecialchars($settings["about_address"]) ?>"></div>
                                    <div class="row g-2">
                                        <div class="col-md-6"><label for="about_email" class="form-label fw-semibold">Email</label><input type="email" class="form-control" id="about_email" name="about_email" value="<?= htmlspecialchars($settings["about_email"]) ?>"></div>
                                        <div class="col-md-6"><label for="about_phone" class="form-label fw-semibold">Phone</label><input type="text" class="form-control" id="about_phone" name="about_phone" value="<?= htmlspecialchars($settings["about_phone"]) ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-pane fade" id="admission-settings" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12 col-xl-9">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h2 class="h5 fw-bold mb-3">Admission Settings</h2>
                                    <div class="mb-3"><label for="admission_title" class="form-label fw-semibold">Admission title</label><input type="text" class="form-control" id="admission_title" name="admission_title" value="<?= htmlspecialchars($settings["admission_title"]) ?>"></div>
                                    <div class="mb-3"><label for="admission_message" class="form-label fw-semibold">Admission message</label><textarea class="form-control" id="admission_message" name="admission_message" rows="3"><?= htmlspecialchars($settings["admission_message"]) ?></textarea></div>
                                    <div class="mb-3"><label for="admission_requirements" class="form-label fw-semibold">Requirements</label><textarea class="form-control" id="admission_requirements" name="admission_requirements" rows="5"><?= htmlspecialchars($settings["admission_requirements"]) ?></textarea>
                                        <div class="form-text">Use one requirement per line.</div>
                                    </div>
                                    <div><label for="admission_deadline" class="form-label fw-semibold">Application deadline</label><input type="text" class="form-control js-date-picker" id="admission_deadline" name="admission_deadline" autocomplete="off" inputmode="numeric" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($settings["admission_deadline"]) ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Save settings</button></div>
        </form>
    </div>
</main>
<?php include "includes/footer.php" ?>