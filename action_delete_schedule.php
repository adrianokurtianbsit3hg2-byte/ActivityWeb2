<?php
header('Content-Type: application/json; charset=utf-8');

include("config.php");
include("firebaseRDB.php");

$db = new firebaseRDB($databaseURL);

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
if ($id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

try {
    // If your firebaseRDB delete signature expects (node, key) use that, otherwise adjust to delete("schedules/{$id}")
    // Attempt two common variations safely:
    if (method_exists($db, 'delete')) {
        // Try delete(node, key) first
        $res = null;
        try {
            $res = $db->delete("schedules", $id);
        } catch (Throwable $e) {
            // fallback to single-arg delete path
            try {
                $res = $db->delete("schedules/{$id}");
            } catch (Throwable $e2) {
                $res = false;
            }
        }

        if ($res) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete returned false']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete method not available on firebaseRDB']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
