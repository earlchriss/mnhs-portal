<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/db.php';
require_once __DIR__ . '/../includes/react.php';
auth_require_login();
header('Cache-Control: no-store');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit('Invalid class.'); }
try {
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT sc.id, sc.subject_id, sc.section_id, t.id AS teacher_id, sub.title, sub.code, sec.name AS section
        FROM schedules sc JOIN teachers t ON t.id = sc.teacher_id
        JOIN subjects sub ON sub.id = sc.subject_id JOIN sections sec ON sec.id = sc.section_id
        WHERE sc.id = :id AND t.moodle_id = :moodle_id');
    $stmt->execute(['id' => $id, 'moodle_id' => auth_user()['id']]);
    $class = $stmt->fetch();
    if (!$class) { http_response_code(403); exit('You do not have access to this class.'); }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!auth_check_csrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Session expired. Refresh the page and try again.'); }
        $student = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $values = $_POST['grade'] ?? null;
        if (!$student || !is_array($values)) {
            $error = 'Invalid student or grades.';
        } else {
            $grades = [];
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $value = $values[$quarter] ?? null;
                if (!is_string($value) || ($value !== '' && (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $value) || (float) $value > 100))) {
                    $error = 'Enter grades from 0 to 100, with at most two decimal places, or leave them blank.';
                    break;
                }
                $grades[$quarter] = $value;
            }
            if (!$error) {
                $pdo->beginTransaction();
                // Lock the enrollment and assignment while saving; never trust student IDs from the form.
                $check = $pdo->prepare('SELECT ss.student_id FROM student_sections ss JOIN schedules sc ON sc.section_id = ss.section_id JOIN teachers t ON t.id = sc.teacher_id WHERE ss.student_id = :student AND sc.id = :class AND t.moodle_id = :moodle FOR UPDATE');
                $check->execute(['student' => $student, 'class' => $id, 'moodle' => auth_user()['id']]);
                if (!$check->fetch()) { $pdo->rollBack(); http_response_code(403); exit('Student is not assigned to this class.'); }
                $upsert = $pdo->prepare('INSERT INTO teacher_quarter_grades (student_id, subject_id, section_id, quarter, grade, updated_by_teacher_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE grade = VALUES(grade), updated_by_teacher_id = VALUES(updated_by_teacher_id), updated_at = CURRENT_TIMESTAMP');
                $delete = $pdo->prepare('DELETE FROM teacher_quarter_grades WHERE student_id = ? AND subject_id = ? AND section_id = ? AND quarter = ?');
                foreach ($grades as $quarter => $value) {
                    $key = [$student, $class['subject_id'], $class['section_id'], $quarter];
                    if ($value === '') $delete->execute($key);
                    else $upsert->execute([...$key, $value, $class['teacher_id']]);
                }
                $pdo->commit();
                header('Location: /teacher/class.php?id=' . $id . '&saved=1', true, 303);
                exit;
            }
        }
    }
    $stmt = $pdo->prepare('SELECT s.id, s.name, g.quarter, g.grade FROM students s JOIN student_sections ss ON ss.student_id = s.id LEFT JOIN teacher_quarter_grades g ON g.student_id = s.id AND g.section_id = ss.section_id AND g.subject_id = :subject WHERE ss.section_id = :section ORDER BY s.name, s.id');
    $stmt->execute(['subject' => $class['subject_id'], 'section' => $class['section_id']]);
    $students = [];
    foreach ($stmt as $row) {
        $students[$row['id']]['name'] = $row['name'];
        if ($row['quarter'] !== null) $students[$row['id']]['grades'][(int) $row['quarter']] = $row['grade'];
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Teacher class: ' . $e->getMessage());
    http_response_code(503);
    exit('Class data is unavailable. Contact an administrator to check the database and grade migration.');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Class grades - Teacher Portal</title><link rel="stylesheet" href="/admin/assets/css/bootstrap.min.css"><link rel="stylesheet" href="/admin/assets/css/custom.css"></head><body class="bg-body-tertiary">
<nav class="moodle-navbar"><a class="text-decoration-none fw-semibold" href="/teacher/">&larr; Teacher Portal</a></nav>
<?php
$studentProps = [];
foreach ($students as $studentId => $student) {
    $studentProps[] = ['id' => (int) $studentId, 'name' => $student['name'], 'grades' => $student['grades'] ?? []];
}
react_mount('teacher-class', [
    'classInfo' => ['title' => $class['title'], 'code' => $class['code'], 'section' => $class['section']],
    'students' => $studentProps,
    'csrf' => auth_csrf_token(),
    'error' => $error,
    'saved' => isset($_GET['saved']),
]);
react_assets();
?></body></html>
