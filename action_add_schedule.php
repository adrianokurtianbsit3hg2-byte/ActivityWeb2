<?php
include("config.php");
include("firebaseRDB.php");

$db = new firebaseRDB($databaseURL);

$faculty_id = $_POST['faculty_id'];
$days = $_POST['days'];

// STEP 1: Get existing schedules
$existing = $db->retrieve("schedules");
$existing = json_decode($existing, true);

$count = 0;
if ($existing) {
    foreach ($existing as $key => $row) {
        if (isset($row['faculty_id']) && $row['faculty_id'] == $faculty_id) {
            $count++;
        }
    }
}

$newKey = $faculty_id . '-' . ($count + 1);

$db->update("schedules", $newKey, [
    "faculty_id"        => $faculty_id,
    "section"           => $_POST['section'],
    "subject_code"      => $_POST['subject_code'],
    "room_mode"         => $_POST['room_mode'],
    "learning_modality" => $_POST['learning_modality'],
    "meeting_link"      => $_POST['meeting_link'],
    "days"              => $days,
    "month_from"        => $_POST['month_from'],
    "month_to"          => $_POST['month_to'],
    "time_from"         => $_POST['time_from'],
    "time_to"           => $_POST['time_to'],
    "is_weekly"         => isset($_POST['is_weekly']) ? 1 : 0
]);

header("Location: schedule.php?success=1");
exit;
?>
