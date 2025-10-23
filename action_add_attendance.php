<?php
include("config.php");
include("firebaseRDB.php");

// Suppress layout-breaking warnings
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

$db = new firebaseRDB($databaseURL);

// Safely extract POST data
$sched_id = $_POST['sched_id'] ?? '';
$faculty_id = $_POST['faculty_id'] ?? '';
$faculty_name = $_POST['faculty_name'] ?? '';
$section = $_POST['section'] ?? '';
$subject_code = $_POST['subject_code'] ?? '';
$room_mode = $_POST['room_mode'] ?? '';
$time_from = $_POST['time_from'] ?? '';
$time_to = $_POST['time_to'] ?? '';
$attendance_date = $_POST['attendance_date'] ?? '';
$learning_modality = $_POST['learning_modality'] ?? '';
$meeting_link = $_POST['meeting_link'] ?? '';
$attendance_status = $_POST['attendance_status'] ?? '';
$dress_code = $_POST['dress_code'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// STEP 1: Get existing attendance records
$existing = $db->retrieve("attendance");
$existing = json_decode($existing, true);

$count = 0;
if ($existing) {
    foreach ($existing as $key => $row) {
        if (strpos($key, $sched_id . '-') === 0) {
            $count++;
        }
    }
}

// STEP 2: Generate new key like scheduleid-1, scheduleid-2, etc.
$newKey = $sched_id . '-' . ($count + 1);

// STEP 3: Save attendance record
$db->update("attendance", $newKey, [
    "sched_id"          => $sched_id,
    "attendance_status" => $attendance_status,
    "dress_code"        => $dress_code,
    "remarks"           => $remarks
]);

// Redirect safely
header("Location: attendance.php?success=1");
exit;
?>

