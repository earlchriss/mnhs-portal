<?php

/**
 * Faculty store for the admin portal (MySQL).
 *
 * Used by faculty.php (add / edit / delete / view ratings).
 * Tables: see faculty.sql (faculty, moodle_feedback_ratings).
 *
 * A faculty member has: id, employee_no, first_name, last_name, email,
 * department, job_title, status (active / inactive) and an optional link to
 * the Moodle teacher (teacher_id). The link is what lets ratings from Moodle
 * Feedback activities reach the right person (see moodle_feedback.php).
 *
 * Ratings go from 1 (poor) to 5 (excellent) and are copied from Moodle by
 * feedback_sync(); nothing here writes them.
 */

require_once __DIR__ . "/../../functions/db.php";

const FACULTY_STATUSES = ["active", "inactive"];
const FACULTY_LIMITS = [
    "employee_no" => 30,
    "first_name"  => 60,
    "last_name"   => 60,
    "email"       => 120,
    "department"  => 80,
    "job_title"   => 80,
];

/** Cast a database row to the shape the page uses. */
function faculty_row(array $row): array
{
    $first = (string) $row["first_name"];
    $last = (string) $row["last_name"];
    return [
        "id"           => (int) $row["id"],
        "employee_no"  => (string) ($row["employee_no"] ?? ""),
        "first_name"   => $first,
        "last_name"    => $last,
        "name"         => trim($first . " " . $last),
        "email"        => (string) ($row["email"] ?? ""),
        "department"   => (string) ($row["department"] ?? ""),
        "job_title"    => (string) ($row["job_title"] ?? ""),
        "status"       => (string) $row["status"],
        "teacher_id"   => $row["teacher_id"] === null ? "" : (int) $row["teacher_id"],
        "teacher_name" => (string) ($row["teacher_name"] ?? ""),
    ];
}

/** Every faculty member, sorted by last name. */
function faculty_all(): array
{
    $sql = "SELECT f.*, t.name AS teacher_name
            FROM faculty f
            LEFT JOIN teachers t ON t.id = f.teacher_id
            ORDER BY f.last_name ASC, f.first_name ASC";
    return array_map("faculty_row", db_connect()->query($sql)->fetchAll());
}

/** One faculty member, or null. */
function faculty_find(int $id): ?array
{
    $stmt = db_connect()->prepare(
        "SELECT f.*, t.name AS teacher_name
         FROM faculty f LEFT JOIN teachers t ON t.id = f.teacher_id
         WHERE f.id = :id"
    );
    $stmt->execute([":id" => $id]);
    $row = $stmt->fetch();
    return $row ? faculty_row($row) : null;
}

/**
 * Validate posted faculty data. $ignore_id is the faculty member being edited
 * (so their own employee number and teacher link do not count as taken).
 * Returns [$errors, $clean]; $clean is only meaningful when $errors is empty.
 */
function faculty_validate(array $input, int $ignore_id = 0): array
{
    $text = fn(string $key) => preg_replace('/\s+/', " ", trim((string) ($input[$key] ?? "")));

    $clean = [
        "employee_no" => $text("employee_no"),
        "first_name"  => $text("first_name"),
        "last_name"   => $text("last_name"),
        "email"       => trim((string) ($input["email"] ?? "")),
        "department"  => $text("department"),
        "job_title"   => $text("job_title"),
        "status"      => (string) ($input["status"] ?? "active"),
        "teacher_id"  => ((int) ($input["teacher_id"] ?? 0)) ?: null,
    ];
    $errors = [];

    if ($clean["first_name"] === "") $errors[] = "Enter the first name.";
    if ($clean["last_name"] === "") $errors[] = "Enter the last name.";

    $names = [
        "employee_no" => "The employee number",
        "first_name"  => "The first name",
        "last_name"   => "The last name",
        "email"       => "The email",
        "department"  => "The department",
        "job_title"   => "The job title",
    ];
    foreach (FACULTY_LIMITS as $field => $max) {
        if (mb_strlen($clean[$field]) > $max) {
            $errors[] = $names[$field] . " must be {$max} characters or fewer.";
        }
    }

    if ($clean["email"] !== "" && !filter_var($clean["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }
    if (!in_array($clean["status"], FACULTY_STATUSES, true)) {
        $errors[] = "Choose a status.";
    }

    if (!$errors) {
        $pdo = db_connect();

        if ($clean["employee_no"] !== "") {
            $stmt = $pdo->prepare("SELECT id FROM faculty WHERE employee_no = :no AND id <> :id LIMIT 1");
            $stmt->execute([":no" => $clean["employee_no"], ":id" => $ignore_id]);
            if ($stmt->fetch()) $errors[] = "Employee number " . $clean["employee_no"] . " is already used.";
        }

        if ($clean["teacher_id"] !== null) {
            $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = :id");
            $stmt->execute([":id" => $clean["teacher_id"]]);
            if (!$stmt->fetch()) {
                $errors[] = "The linked LMS teacher no longer exists.";
            } else {
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM faculty WHERE teacher_id = :t AND id <> :id LIMIT 1");
                $stmt->execute([":t" => $clean["teacher_id"], ":id" => $ignore_id]);
                if ($other = $stmt->fetch()) {
                    $errors[] = "That LMS teacher is already linked to " . $other["first_name"] . " " . $other["last_name"] . ".";
                }
            }
        }
    }

    return [$errors, $clean];
}

/** Empty text is stored as NULL so the unique keys ignore it. */
function faculty_params(array $clean): array
{
    $blank = fn($v) => $v === "" ? null : $v;
    return [
        ":employee_no" => $blank($clean["employee_no"]),
        ":first_name"  => $clean["first_name"],
        ":last_name"   => $clean["last_name"],
        ":email"       => $blank($clean["email"]),
        ":department"  => $blank($clean["department"]),
        ":job_title"   => $blank($clean["job_title"]),
        ":status"      => $clean["status"],
        ":teacher_id"  => $clean["teacher_id"],
    ];
}

/** Add a faculty member. Returns the new id. */
function faculty_create(array $clean): int
{
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "INSERT INTO faculty (employee_no, first_name, last_name, email, department, job_title, status, teacher_id)
         VALUES (:employee_no, :first_name, :last_name, :email, :department, :job_title, :status, :teacher_id)"
    );
    $stmt->execute(faculty_params($clean));
    return (int) $pdo->lastInsertId();
}

/** Save changes to a faculty member. */
function faculty_update(int $id, array $clean): void
{
    $stmt = db_connect()->prepare(
        "UPDATE faculty
         SET employee_no = :employee_no, first_name = :first_name, last_name = :last_name,
             email = :email, department = :department, job_title = :job_title,
             status = :status, teacher_id = :teacher_id
         WHERE id = :id"
    );
    $stmt->execute(faculty_params($clean) + [":id" => $id]);
}

/**
 * Delete a faculty member. Their synced ratings are removed with them, which is
 * safe: the answers stay in Moodle and come back if the person is added and linked again.
 */
function faculty_delete(int $id): void
{
    $stmt = db_connect()->prepare("DELETE FROM faculty WHERE id = :id");
    $stmt->execute([":id" => $id]);
}

/** Terms that have synced ratings, newest first. */
function faculty_eval_terms(): array
{
    return array_map("strval", db_connect()->query("SELECT DISTINCT term FROM moodle_feedback_ratings ORDER BY term DESC")->fetchAll(PDO::FETCH_COLUMN));
}

/** Sections (Moodle course names) that have synced ratings. */
function faculty_eval_sections(): array
{
    return array_map("strval", db_connect()->query("SELECT DISTINCT section FROM moodle_feedback_ratings ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN));
}

/** When ratings were last copied from Moodle ("Y-m-d H:i:s"), or null if never. */
function faculty_last_sync(): ?string
{
    $value = db_connect()->query("SELECT MAX(synced_at) FROM moodle_feedback_ratings")->fetchColumn();
    return $value ? (string) $value : null;
}

/**
 * Ratings per faculty member, optionally limited to one term and/or section.
 * Returns [faculty_id => [
 *   "count" => responses, "avg" => average rating (1-5, 2 decimals),
 *   "breakdown" => [["term", "section", "count", "avg"], ...]
 * ]]. Faculty with no matching rated answers are not in the list.
 */
function faculty_ratings(string $term = "", string $section = ""): array
{
    $where = [];
    $params = [];
    if ($term !== "") {
        $where[] = "term = :term";
        $params[":term"] = $term;
    }
    if ($section !== "") {
        $where[] = "section = :section";
        $params[":section"] = $section;
    }
    $clause = $where ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT faculty_id, term, section,
                   SUM(responses) AS responses, SUM(answers) AS answers, SUM(rating_total) AS total
            FROM moodle_feedback_ratings
            $clause
            GROUP BY faculty_id, term, section
            HAVING SUM(answers) > 0
            ORDER BY term DESC, section ASC";
    $stmt = db_connect()->prepare($sql);
    $stmt->execute($params);

    $sums = [];
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row["faculty_id"];
        $responses = (int) $row["responses"];
        $total = (float) $row["total"];
        $answers = (int) $row["answers"];

        $sums[$id] = $sums[$id] ?? ["total" => 0.0, "answers" => 0];
        $sums[$id]["total"] += $total;
        $sums[$id]["answers"] += $answers;

        $out[$id]["count"] = ($out[$id]["count"] ?? 0) + $responses;
        $out[$id]["breakdown"][] = [
            "term"    => (string) $row["term"],
            "section" => (string) $row["section"],
            "count"   => $responses,
            "avg"     => round($total / $answers, 2),
        ];
    }
    foreach ($out as $id => &$entry) {
        $entry["avg"] = round($sums[$id]["total"] / $sums[$id]["answers"], 2);
    }
    unset($entry);

    return $out;
}

/**
 * The Feedback activities seen in the last sync (one row each), for the "Add question" modal.
 * Returns [["feedback_id", "course_id", "cm_id", "name", "section"], ...].
 */
function faculty_feedback_activities(): array
{
    $sql = "SELECT feedback_id, MAX(course_id) AS course_id, MAX(cm_id) AS cm_id,
                   MAX(feedback_name) AS name, MAX(section) AS section
            FROM moodle_feedback_ratings
            GROUP BY feedback_id
            ORDER BY section ASC, name ASC";
    return array_map(fn($row) => [
        "feedback_id" => (int) $row["feedback_id"],
        "course_id"   => (int) $row["course_id"],
        "cm_id"       => $row["cm_id"] === null ? 0 : (int) $row["cm_id"],
        "name"        => (string) $row["name"],
        "section"     => (string) $row["section"],
    ], db_connect()->query($sql)->fetchAll());
}
