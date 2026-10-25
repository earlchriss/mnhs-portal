<?php

/**
 * Registered students and the section each one is assigned to (MySQL).
 *
 * Tables: see students.sql
 *   students         (id, name, email)           <- the registered students
 *   student_sections (student_id, section_id)    <- which section each student is in
 *
 * If your registered users already live in another table (for example a users or
 * Moodle table), change STUDENTS_QUERY below. It only has to return the columns
 * id, name and email.
 */

require_once __DIR__ . "/../../functions/db.php";

const STUDENTS_QUERY = "SELECT id, name, email FROM students ORDER BY name ASC";

/** Every registered student as ["id" => int, "name" => string, "email" => string]. */
function students_registered(): array
{
    $out = [];
    foreach (db_connect()->query(STUDENTS_QUERY)->fetchAll() as $row) {
        $out[] = [
            "id"    => (int) $row["id"],
            "name"  => (string) $row["name"],
            "email" => (string) ($row["email"] ?? ""),
        ];
    }
    return $out;
}

/** [student id => section name] for the students who already have a section. */
function students_assignments(): array
{
    $sql = "SELECT ss.student_id, s.name
            FROM student_sections ss JOIN sections s ON s.id = ss.section_id";
    $out = [];
    foreach (db_connect()->query($sql)->fetchAll() as $row) {
        $out[(int) $row["student_id"]] = (string) $row["name"];
    }
    return $out;
}

/** Put the given students in a section (replaces their previous section). */
function students_assign(array $student_ids, string $section): void
{
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "INSERT INTO student_sections (student_id, section_id)
         VALUES (:student_id, (SELECT id FROM sections WHERE name = :section))
         ON DUPLICATE KEY UPDATE section_id = VALUES(section_id)"
    );

    $pdo->beginTransaction();
    try {
        foreach ($student_ids as $id) {
            $stmt->execute([":student_id" => (int) $id, ":section" => $section]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
