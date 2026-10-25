<?php

/**
 * Teachers from Moodle (LMS) for the schedule dropdown.
 *
 * Moodle has no "list everyone with role X" web service, so this reads the
 * courses, then each course's enrolled users, and keeps those whose role in
 * the course is one of MOODLE_TEACHER_ROLES. Teachers are copied into the local
 * `teachers` table (keyed by moodle_id) so schedules can point at them.
 *
 * The Moodle web-service token (MOODLE_WSTOKEN) needs these functions enabled:
 *   core_course_get_courses, core_enrol_get_enrolled_users
 *
 * Note: a teacher only shows up once enrolled as a teacher in at least one course.
 */

require_once __DIR__ . "/moodle_users.php";
require_once __DIR__ . "/../../functions/db.php";

// Moodle role shortnames that count as "teacher".
// "editingteacher" is shown as "Teacher" in Moodle; "teacher" is "Non-editing teacher".
const MOODLE_TEACHER_ROLES = ["editingteacher"];

// How long (seconds) before the list is fetched from Moodle again.
const TEACHER_SYNC_TTL = 600;

/**
 * Ask Moodle for every teacher.
 * Returns ["ok" => true, "teachers" => [moodle_id => ["name", "email"]], "partial" => bool]
 * or an error array from moodle_users_call().
 */
function moodle_fetch_teachers(): array
{
    $courses = moodle_users_call("core_course_get_courses", []);
    if (!$courses["ok"]) return $courses;

    $teachers = [];
    $partial = false;

    foreach ((array) $courses["data"] as $course) {
        $courseId = (int) ($course["id"] ?? 0);
        if ($courseId <= 1) continue; // 1 is the site front page, not a course

        $enrolled = moodle_users_call("core_enrol_get_enrolled_users", ["courseid" => $courseId]);
        if (!$enrolled["ok"]) {
            $partial = true;
            continue;
        }

        foreach ((array) $enrolled["data"] as $user) {
            $isTeacher = false;
            foreach ((array) ($user["roles"] ?? []) as $role) {
                if (in_array($role["shortname"] ?? "", MOODLE_TEACHER_ROLES, true)) {
                    $isTeacher = true;
                    break;
                }
            }
            $id = (int) ($user["id"] ?? 0);
            if (!$isTeacher || $id <= 0) continue;

            $name = trim((string) ($user["fullname"] ?? ""));
            if ($name === "") {
                $name = trim(($user["firstname"] ?? "") . " " . ($user["lastname"] ?? ""));
            }
            $teachers[$id] = ["name" => $name, "email" => (string) ($user["email"] ?? "")];
        }
    }

    return ["ok" => true, "teachers" => $teachers, "partial" => $partial];
}

/**
 * Copy Moodle's teachers into the local table (at most once per TEACHER_SYNC_TTL
 * per session unless $force). Returns an error/warning message, or null if all is well.
 * On a problem the teachers already in the table are still usable.
 */
function teachers_sync(bool $force = false): ?string
{
    $last = (int) ($_SESSION["teachers_synced_at"] ?? 0);
    if (!$force && $last && time() - $last < TEACHER_SYNC_TTL) {
        return null;
    }

    $result = moodle_fetch_teachers();
    if (!$result["ok"]) {
        return "Could not get teachers from LMS: " . $result["message"] . " Showing the teachers saved earlier.";
    }

    $pdo = db_connect();
    $upsert = $pdo->prepare(
        "INSERT INTO teachers (moodle_id, name, email) VALUES (:moodle_id, :name, :email)
         ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email)"
    );
    foreach ($result["teachers"] as $moodleId => $teacher) {
        $upsert->execute([":moodle_id" => $moodleId, ":name" => $teacher["name"], ":email" => $teacher["email"]]);
    }

    // Drop teachers that are no longer in Moodle (and old sample rows), but never
    // one that already has a schedule. Skipped when the list may be incomplete.
    if (!$result["partial"] && $result["teachers"]) {
        $keep = implode(",", array_map("intval", array_keys($result["teachers"])));
        $pdo->exec(
            "DELETE FROM teachers
             WHERE (moodle_id IS NULL OR moodle_id NOT IN ($keep))
               AND id NOT IN (SELECT teacher_id FROM schedules)"
        );
    }

    if ($result["partial"]) {
        return "Some LMS courses could not be read, so the teacher list may be incomplete.";
    }

    $_SESSION["teachers_synced_at"] = time();
    return null;
}
