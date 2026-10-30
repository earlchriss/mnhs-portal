CREATE TABLE IF NOT EXISTS teacher_quarter_grades (
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NOT NULL,
    quarter TINYINT UNSIGNED NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    updated_by_teacher_id INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, subject_id, section_id, quarter),
    CONSTRAINT fk_tqg_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_tqg_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_tqg_section FOREIGN KEY (section_id) REFERENCES sections(id),
    CONSTRAINT fk_tqg_teacher FOREIGN KEY (updated_by_teacher_id) REFERENCES teachers(id),
    CONSTRAINT chk_tqg_quarter CHECK (quarter BETWEEN 1 AND 4),
    CONSTRAINT chk_tqg_grade CHECK (grade BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
