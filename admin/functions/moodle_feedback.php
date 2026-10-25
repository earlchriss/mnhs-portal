<?php

/**
 * Teacher ratings from Moodle Feedback activities.
 *
 * Students answer the Feedback activity inside Moodle. This file copies the
 * totals (not the individual students) into the local table
 * `moodle_feedback_ratings`, so the Faculty page can show averages quickly.
 *
 * How a rating is matched to a faculty member:
 *   - Teacher: the people enrolled in the Feedback's course with the role in
 *     FEEDBACK_TEACHER_ROLES. Each one is matched to a faculty member through
 *     teachers.moodle_id -> faculty.teacher_id (the "Linked LMS teacher" field).
 *   - Section: the Moodle course name.
 *   - Term: the current term passed to feedback_sync(). A row keeps the term it
 *     was first synced under, so changing the term later does not relabel old ratings.
 *
 * Only rating questions count (FEEDBACK_RATING_TYPES). In Moodle these are the
 * "Multiple choice (rated)" questions, whose options look like 1####Poor.
 * An option with a value of 0 or less is treated as "not applicable" and ignored.
 *
 * The Moodle web-service token (MOODLE_WSTOKEN) needs these functions enabled:
 *   core_course_get_courses, core_enrol_get_enrolled_users,
 *   mod_feedback_get_feedbacks_by_courses, mod_feedback_get_analysis,
 *   mod_feedback_get_items
 * and its user needs mod/feedback:viewanalysepage in those courses (a Manager
 * or administrator has it).
 */

require_once __DIR__ . "/moodle_users.php";
require_once __DIR__ . "/../../functions/db.php";

// Moodle Feedback question types that hold a rating.
const FEEDBACK_RATING_TYPES = ["multichoicerated"];

// Moodle role shortnames that count as the teacher of a course.
const FEEDBACK_TEACHER_ROLES = ["editingteacher"];

/**
 * Turn a rated question's option list into [lowercase label => value].
 * Options are separated by "|" and written "value####label"; without a value
 * the option's position (1, 2, 3 ...) is used.
 */
function feedback_rating_options(string $presentation): array
{
    $presentation = preg_replace('/^[rdc]>>>>>/', "", trim($presentation));
    $map = [];
    $position = 0;
    foreach (explode("|", $presentation) as $option) {
        $option = trim($option);
        if ($option === "") continue;
        $position++;

        $value = (float) $position;
        $label = $option;
        if (str_contains($option, "####")) {
            [$raw, $label] = explode("####", $option, 2);
            if (is_numeric(trim($raw))) $value = (float) trim($raw);
        }
        $map[feedback_clean_label($label)] = $value;
    }
    return $map;
}

function feedback_clean_label(string $text): string
{
    return mb_strtolower(trim(html_entity_decode(strip_tags($text), ENT_QUOTES, "UTF-8")));
}

/** [item id => presentation] for one Feedback, used only when the analysis does not include it. */
function feedback_item_presentations(int $feedbackId): array
{
    $result = moodle_users_call("mod_feedback_get_items", ["feedbackid" => $feedbackId]);
    if (!$result["ok"]) return [];
    $out = [];
    foreach ((array) ($result["data"]["items"] ?? []) as $item) {
        $out[(int) ($item["id"] ?? 0)] = (string) ($item["presentation"] ?? "");
    }
    return $out;
}

/**
 * Read one Feedback's answers.
 * Returns ["ok" => true, "responses" => int, "answers" => int, "total" => float, "unmatched" => int]
 * or an error array. average rating = total / answers.
 */
function feedback_analyse(int $feedbackId, int $courseId): array
{
    $result = moodle_users_call("mod_feedback_get_analysis", ["feedbackid" => $feedbackId, "courseid" => $courseId]);
    if (!$result["ok"]) return $result;

    $data = (array) $result["data"];
    $answers = 0;
    $total = 0.0;
    $unmatched = 0;
    $presentations = null; // loaded only if needed

    foreach ((array) ($data["itemsdata"] ?? []) as $entry) {
        $item = (array) ($entry["item"] ?? []);
        if (!in_array($item["typ"] ?? "", FEEDBACK_RATING_TYPES, true)) continue;

        $presentation = (string) ($item["presentation"] ?? "");
        if ($presentation === "") {
            $presentations ??= feedback_item_presentations($feedbackId);
            $presentation = $presentations[(int) ($item["id"] ?? 0)] ?? "";
        }
        $options = feedback_rating_options($presentation);

        // Each entry is one answer option, usually a JSON string: answertext, answercount, ...
        foreach ((array) ($entry["data"] ?? []) as $raw) {
            $row = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($row)) continue;

            $count = (int) ($row["answercount"] ?? 0);
            if ($count <= 0) continue;

            // Moodle may print the option as "(3) Good": split the number from the label.
            $text = trim(html_entity_decode(strip_tags((string) ($row["answertext"] ?? "")), ENT_QUOTES, "UTF-8"));
            $shown = null;
            if (preg_match('/^\(\s*(-?\d+(?:\.\d+)?)\s*\)\s*(.*)$/s', $text, $m)) {
                $shown = (float) $m[1];
                $text = $m[2];
            }

            $label = feedback_clean_label($text);
            if (isset($options[$label])) {
                $value = $options[$label];
            } elseif (isset($row["value"]) && is_numeric($row["value"])) {
                $value = (float) $row["value"];
            } else {
                $value = $shown;
            }

            if ($value === null) {
                $unmatched += $count;
                continue;
            }
            if ($value <= 0) continue; // "not applicable"

            $answers += $count;
            $total += $value * $count;
        }
    }

    return [
        "ok"        => true,
        "responses" => (int) ($data["completedcount"] ?? 0),
        "answers"   => $answers,
        "total"     => $total,
        "unmatched" => $unmatched,
    ];
}

/** [moodle user id => faculty id] for faculty linked to a Moodle teacher. */
function feedback_faculty_by_moodle_id(): array
{
    $sql = "SELECT t.moodle_id, f.id AS faculty_id
            FROM faculty f JOIN teachers t ON t.id = f.teacher_id
            WHERE t.moodle_id IS NOT NULL";
    $out = [];
    foreach (db_connect()->query($sql)->fetchAll() as $row) {
        $out[(int) $row["moodle_id"]] = (int) $row["faculty_id"];
    }
    return $out;
}

/** Moodle user ids that teach a course, or an error array. */
function feedback_course_teachers(int $courseId): array
{
    $enrolled = moodle_users_call("core_enrol_get_enrolled_users", ["courseid" => $courseId]);
    if (!$enrolled["ok"]) return $enrolled;

    $ids = [];
    foreach ((array) $enrolled["data"] as $user) {
        foreach ((array) ($user["roles"] ?? []) as $role) {
            if (in_array($role["shortname"] ?? "", FEEDBACK_TEACHER_ROLES, true)) {
                $ids[] = (int) ($user["id"] ?? 0);
                break;
            }
        }
    }
    return ["ok" => true, "ids" => array_filter($ids)];
}

/**
 * Copy the ratings from Moodle into moodle_feedback_ratings.
 *
 * Returns ["ok" => false, "message" => ...] when Moodle cannot be read at all, otherwise
 * ["ok" => true, "feedbacks" => Feedback activities saved, "ratings" => rows saved,
 *  "skipped" => [course names with no linked faculty], "failed" => activities that could not be read,
 *  "error" => first read error, "unmatched" => answers that could not be given a value].
 */
function feedback_sync(string $term): array
{
    if (function_exists("set_time_limit")) @set_time_limit(300);

    $faculty = feedback_faculty_by_moodle_id();
    if (!$faculty) {
        return ["ok" => false, "message" => "No faculty member is linked to an LMS teacher yet. Edit a faculty member and choose a Linked LMS teacher first."];
    }

    $courses = moodle_users_call("core_course_get_courses", []);
    if (!$courses["ok"]) return ["ok" => false, "message" => "Could not read the LMS courses: " . $courses["message"]];

    $names = [];
    foreach ((array) $courses["data"] as $course) {
        $id = (int) ($course["id"] ?? 0);
        if ($id > 1) $names[$id] = trim((string) ($course["fullname"] ?? "")) ?: ("Course " . $id);
    }

    $stats = ["ok" => true, "feedbacks" => 0, "ratings" => 0, "skipped" => [], "failed" => 0, "error" => "", "unmatched" => 0];
    $started = date("Y-m-d H:i:s");
    $partial = false;
    $byCourse = [];

    if ($names) {
        $found = moodle_users_call("mod_feedback_get_feedbacks_by_courses", ["courseids" => array_keys($names)]);
        if (!$found["ok"]) return ["ok" => false, "message" => "Could not read the LMS Feedback activities: " . $found["message"]];
        foreach ((array) ($found["data"]["feedbacks"] ?? []) as $feedback) {
            $byCourse[(int) ($feedback["course"] ?? 0)][] = $feedback;
        }
    }

    $upsert = db_connect()->prepare(
        "INSERT INTO moodle_feedback_ratings
            (faculty_id, feedback_id, course_id, cm_id, feedback_name, section, term, responses, answers, rating_total, synced_at)
         VALUES (:faculty_id, :feedback_id, :course_id, :cm_id, :feedback_name, :section, :term, :responses, :answers, :rating_total, :synced_at)
         ON DUPLICATE KEY UPDATE
            course_id = VALUES(course_id), cm_id = VALUES(cm_id), feedback_name = VALUES(feedback_name), section = VALUES(section),
            responses = VALUES(responses), answers = VALUES(answers), rating_total = VALUES(rating_total),
            synced_at = VALUES(synced_at)"
    );

    foreach ($byCourse as $courseId => $feedbacks) {
        if (!isset($names[$courseId])) continue;

        $teachers = feedback_course_teachers($courseId);
        if (!$teachers["ok"]) {
            $partial = true;
            $stats["failed"] += count($feedbacks);
            $stats["error"] = $stats["error"] ?: $teachers["message"];
            continue;
        }
        $facultyIds = [];
        foreach ($teachers["ids"] as $moodleId) {
            if (isset($faculty[$moodleId])) $facultyIds[$faculty[$moodleId]] = true;
        }
        if (!$facultyIds) {
            $stats["skipped"][] = $names[$courseId];
            continue;
        }

        foreach ($feedbacks as $feedback) {
            $feedbackId = (int) ($feedback["id"] ?? 0);
            if ($feedbackId <= 0) continue;

            $analysis = feedback_analyse($feedbackId, $courseId);
            if (!$analysis["ok"]) {
                $partial = true;
                $stats["failed"]++;
                $stats["error"] = $stats["error"] ?: $analysis["message"];
                continue;
            }

            foreach (array_keys($facultyIds) as $facultyId) {
                $upsert->execute([
                    ":faculty_id"    => $facultyId,
                    ":feedback_id"   => $feedbackId,
                    ":course_id"     => $courseId,
                    ":cm_id"         => ((int) ($feedback["coursemodule"] ?? 0)) ?: null,
                    ":feedback_name" => mb_substr(trim((string) ($feedback["name"] ?? "Feedback")), 0, 255),
                    ":section"       => mb_substr($names[$courseId], 0, 255),
                    ":term"          => $term,
                    ":responses"     => $analysis["responses"],
                    ":answers"       => $analysis["answers"],
                    ":rating_total"  => $analysis["total"],
                    ":synced_at"     => $started,
                ]);
                $stats["ratings"]++;
            }
            $stats["feedbacks"]++;
            $stats["unmatched"] += $analysis["unmatched"];
        }
    }

    // Remove ratings whose Feedback or teacher link is gone. Skipped when anything could not be read.
    if (!$partial && $stats["feedbacks"] > 0) {
        $delete = db_connect()->prepare("DELETE FROM moodle_feedback_ratings WHERE synced_at < :started");
        $delete->execute([":started" => $started]);
    }

    return $stats;
}

/** A [type, message] pair for the page banner after a sync. */
function feedback_sync_notice(array $result): array
{
    if (!$result["ok"]) return ["danger", $result["message"]];

    if ($result["feedbacks"] === 0 && !$result["failed"]) {
        $message = "No Feedback activities were found in the LMS courses taught by linked faculty.";
    } else {
        $message = "Synced " . $result["feedbacks"] . " Feedback activit" . ($result["feedbacks"] === 1 ? "y" : "ies") . ".";
    }
    $notes = [];
    if ($result["failed"]) {
        $notes[] = $result["failed"] . " could not be read (" . ($result["error"] ?: "unknown error") . "). Nothing was removed.";
    }
    if ($result["skipped"]) {
        $list = array_slice($result["skipped"], 0, 5);
        $more = count($result["skipped"]) - count($list);
        $notes[] = "Skipped " . count($result["skipped"]) . " course" . (count($result["skipped"]) === 1 ? "" : "s")
            . " whose teacher is not linked to a faculty member: " . implode(", ", $list) . ($more > 0 ? " and {$more} more" : "") . ".";
    }
    if ($result["unmatched"]) {
        $notes[] = $result["unmatched"] . " answers could not be matched to a rating value and were left out.";
    }

    $type = ($notes || $result["feedbacks"] === 0) ? "warning" : "success";
    return [$type, trim($message . " " . implode(" ", $notes))];
}

/** The Moodle site address (no trailing slash), taken from MOODLE_URL or from MOODLE_REST_URL. */
function feedback_moodle_base(): string
{
    $url = getenv("MOODLE_URL") ?: (getenv("MOODLE_REST_URL") ?: "https://lms.mabolonationalhs.me/webservice/rest/server.php");
    return rtrim(preg_replace('#/webservice/.*$#', "", $url), "/");
}

/** Moodle page where questions are added to a Feedback (falls back to its course page). */
function feedback_editor_url(array $activity): string
{
    $base = feedback_moodle_base();
    if (!empty($activity["cm_id"])) return $base . "/mod/feedback/edit.php?id=" . (int) $activity["cm_id"];
    return $base . "/course/view.php?id=" . (int) $activity["course_id"];
}
