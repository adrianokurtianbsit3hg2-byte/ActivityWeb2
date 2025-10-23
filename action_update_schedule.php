<?php
header('Content-Type: application/json; charset=utf-8');

include("config.php");
include("firebaseRDB.php");

$db = new firebaseRDB($databaseURL);

// required id
$id = isset($_POST['id']) ? trim($_POST['id']) : '';
if ($id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

// Build payload from allowed fields (same fields you use in add)
$payload = [
    "faculty_id"        => isset($_POST['faculty_id']) ? $_POST['faculty_id'] : null,
    "section"           => isset($_POST['section']) ? $_POST['section'] : null,
    "subject_code"      => isset($_POST['subject_code']) ? $_POST['subject_code'] : null,
    "room_mode"         => isset($_POST['room_mode']) ? $_POST['room_mode'] : null,
    "learning_modality" => isset($_POST['learning_modality']) ? $_POST['learning_modality'] : null,
    "meeting_link"      => isset($_POST['meeting_link']) ? $_POST['meeting_link'] : null,
    "days"              => isset($_POST['days']) ? $_POST['days'] : null,
    "month_from"        => isset($_POST['month_from']) ? $_POST['month_from'] : null,
    "month_to"          => isset($_POST['month_to']) ? $_POST['month_to'] : null,
    "time_from"         => isset($_POST['time_from']) ? $_POST['time_from'] : null,
    "time_to"           => isset($_POST['time_to']) ? $_POST['time_to'] : null,
    "is_weekly"         => isset($_POST['is_weekly']) ? (int)$_POST['is_weekly'] : 0
];

// Remove null values so we only update provided fields
$payload = array_filter($payload, function($v) { return $v !== null; });

// Nothing to update guard
if (empty($payload)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit;
}

try {
    // Matches your add style: update(node, key, payload)
    $result = $db->update("schedules", $id, $payload);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update returned false']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
