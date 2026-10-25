<?php

/**
 * Schedule store for the admin portal (MySQL, cached in Redis).
 *
 * Used by schedule.php (assign / remove / view) and subjects.php
 * (which sections each subject is scheduled in).
 *
 * Tables: see schedule.sql (teachers, sections, rooms, schedules).
 * Rows are returned in the same shape the pages already used:
 *   schedule = [id, subject_id, teacher_id, section (name), days[], start "HH:MM", end "HH:MM", room (name)]
 *
 * Reads go through Redis (see functions/redis_cache.php) and fall back to the
 * database when Redis is not available. Every write below clears the cached
 * values it makes out of date.
 */

require_once __DIR__ . "/../../functions/db.php";
require_once __DIR__ . "/../../functions/redis_cache.php";

/** Seconds a cached list stays valid if nothing clears it sooner. */
const SCHEDULE_CACHE_TTL = 300;

/** [id => name] of every teacher. Cleared when the Moodle teacher sync runs. */
function schedule_teachers(): array
{
    return rcache_remember("schedule:teachers", SCHEDULE_CACHE_TTL, function () {
        $out = [];
        foreach (db_connect()->query("SELECT id, name FROM teachers ORDER BY name ASC")->fetchAll() as $row) {
            $out[(int) $row["id"]] = (string) $row["name"];
        }
        return $out;
    });
}

/** List of section names. */
function schedule_sections(): array
{
    return rcache_remember("schedule:sections", SCHEDULE_CACHE_TTL, function () {
        return array_map("strval", db_connect()->query("SELECT name FROM sections ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN));
    });
}

/** List of room names. */
function schedule_rooms(): array
{
    return rcache_remember("schedule:rooms", SCHEDULE_CACHE_TTL, function () {
        return array_map("strval", db_connect()->query("SELECT name FROM rooms ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN));
    });
}

/** Add a section. Returns the new id. Throws PDOException (code 23000) if the name already exists. */
function section_create(string $name): int
{
    $pdo = db_connect();
    $stmt = $pdo->prepare("INSERT INTO sections (name) VALUES (:name)");
    $stmt->execute([":name" => $name]);
    rcache_forget("schedule:sections");
    return (int) $pdo->lastInsertId();
}

/** Add a room. Returns the new id. Throws PDOException (code 23000) if the name already exists. */
function room_create(string $name): int
{
    $pdo = db_connect();
    $stmt = $pdo->prepare("INSERT INTO rooms (name) VALUES (:name)");
    $stmt->execute([":name" => $name]);
    rcache_forget("schedule:rooms");
    return (int) $pdo->lastInsertId();
}

/** Every schedule entry. */
function schedules_all(): array
{
    return rcache_remember("schedule:all", SCHEDULE_CACHE_TTL, function () {
        $sql = "SELECT sc.id, sc.subject_id, sc.teacher_id, s.name AS section, r.name AS room,
                       sc.days, TIME_FORMAT(sc.start_time, '%H:%i') AS `start`, TIME_FORMAT(sc.end_time, '%H:%i') AS `end`
                FROM schedules sc
                JOIN sections s ON s.id = sc.section_id
                JOIN rooms r ON r.id = sc.room_id
                ORDER BY sc.id ASC";

        $rows = [];
        foreach (db_connect()->query($sql)->fetchAll() as $row) {
            $rows[] = [
                "id"         => (int) $row["id"],
                "subject_id" => (int) $row["subject_id"],
                "teacher_id" => (int) $row["teacher_id"],
                "section"    => (string) $row["section"],
                "days"       => $row["days"] === "" ? [] : explode(",", $row["days"]),
                "start"      => (string) $row["start"],
                "end"        => (string) $row["end"],
                "room"       => (string) $row["room"],
            ];
        }
        return $rows;
    });
}

/** Forget everything that is built from the schedules table. */
function schedule_cache_clear(): void
{
    rcache_forget("schedule:all", "schedule:sections_by_subject");
}

/**
 * Save a new schedule entry. $data uses the same keys as above
 * (section and room are names that must already exist). Returns the new id.
 */
function schedule_create(array $data): int
{
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "INSERT INTO schedules (subject_id, teacher_id, section_id, room_id, days, start_time, end_time)
         VALUES (:subject_id, :teacher_id,
                 (SELECT id FROM sections WHERE name = :section),
                 (SELECT id FROM rooms WHERE name = :room),
                 :days, :start_time, :end_time)"
    );
    $stmt->execute([
        ":subject_id" => (int) $data["subject_id"],
        ":teacher_id" => (int) $data["teacher_id"],
        ":section"    => $data["section"],
        ":room"       => $data["room"],
        ":days"       => implode(",", $data["days"]),
        ":start_time" => $data["start"] . ":00",
        ":end_time"   => $data["end"] . ":00",
    ]);
    schedule_cache_clear();
    return (int) $pdo->lastInsertId();
}

/** Remove a schedule entry. */
function schedule_delete(int $id): void
{
    $stmt = db_connect()->prepare("DELETE FROM schedules WHERE id = :id");
    $stmt->execute([":id" => $id]);
    schedule_cache_clear();
}

/** [subject_id => [section names]] for the Subjects page. */
function schedules_sections_by_subject(): array
{
    return rcache_remember("schedule:sections_by_subject", SCHEDULE_CACHE_TTL, function () {
        $sql = "SELECT DISTINCT sc.subject_id, s.name
                FROM schedules sc JOIN sections s ON s.id = sc.section_id
                ORDER BY s.name ASC";
        $out = [];
        foreach (db_connect()->query($sql)->fetchAll() as $row) {
            $out[(int) $row["subject_id"]][] = (string) $row["name"];
        }
        return $out;
    });
}
