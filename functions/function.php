<?php
$schoolName = "Mabolo National High School";


/* Sample data: replace with database queries */
$student = [
    "first_name" => "Edward",
    "id"         => "2021-01234",
    "program"    => "BS Computer Science",
    "year"       => "3rd Year",
    "section"    => "BSCS 3-A",
];

$term = "1st Semester, AY 2026-2027";

$enrollment = [
    "status"  => "Officially Enrolled",
    "balance" => 0.00,
    "steps"   => [
        ["label" => "Subjects selected", "done" => true],
        ["label" => "Assessed",          "done" => true],
        ["label" => "Payment verified",  "done" => true],
        ["label" => "Enrolled",          "done" => true],
    ],
];

$subjects = [
    ["code" => "CS 301",   "title" => "Data Structures and Algorithms", "units" => 3, "midterm" => "1.50", "final" => null, "instructor" => "A. Reyes",     "days" => ["Mon", "Wed"], "time" => "8:00 - 9:30 AM",   "room" => "CCS 301"],
    ["code" => "CS 305",   "title" => "Database Management Systems",    "units" => 3, "midterm" => "1.75", "final" => null, "instructor" => "M. Santos",    "days" => ["Tue", "Thu"], "time" => "10:00 - 11:30 AM", "room" => "CCS Lab 2"],
    ["code" => "CS 312",   "title" => "Web Systems and Technologies",   "units" => 3, "midterm" => "1.25", "final" => null, "instructor" => "J. Villanueva", "days" => ["Mon", "Wed"], "time" => "1:00 - 2:30 PM",   "room" => "CCS Lab 1"],
    ["code" => "MATH 210", "title" => "Discrete Mathematics",           "units" => 3, "midterm" => "2.00", "final" => null, "instructor" => "L. Garcia",    "days" => ["Tue", "Thu"], "time" => "2:30 - 4:00 PM",   "room" => "RM 204"],
    ["code" => "GE 104",   "title" => "Ethics",                         "units" => 3, "midterm" => null,   "final" => null, "instructor" => "R. Dela Cruz", "days" => ["Fri"],        "time" => "9:00 AM - 12:00 PM", "room" => "RM 118"],
    ["code" => "PE 4",     "title" => "Physical Education 4",           "units" => 2, "midterm" => "1.25", "final" => null, "instructor" => "T. Flores",    "days" => ["Sat"],        "time" => "8:00 - 10:00 AM",    "room" => "Gym"],
];

$total_units = array_sum(array_column($subjects, "units"));
$previous_gpa = "1.62";
$today = date("D");

$announcements = [
    ["title" => "Midterm grades are now posted",        "date" => "Oct 18", "tag" => "Grades"],
    ["title" => "Enrollment for next term opens Nov 4", "date" => "Oct 16", "tag" => "Registrar"],
    ["title" => "Graduation application deadline",      "date" => "Oct 15", "tag" => "Registrar"],
];

$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 18 ? "Good afternoon" : "Good evening");

function isActive($page)
{
    $currentPage = basename($_SERVER['PHP_SELF']);

    return $currentPage === $page ? 'active' : '';
}
