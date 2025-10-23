<?php
include("config.php");
include("firebaseRDB.php");

$db = new firebaseRDB($databaseURL);
$id = $_POST['id_no'];

$update = $db->update("faculty", $id, [
    "Id_No"           => $_POST['id_no'],
    "Last_Name"       => $_POST['last_name'],
    "First_Name"      => $_POST['first_name'],
    "Middle_Name"     => $_POST['middle_name'],
    "Gender"          => $_POST['gender'],
    "Faculty_Rank"    => $_POST['faculty_rank'],
    "Faculty_Status"  => $_POST['faculty_status'],
    "Department"      => $_POST['department']
]);

header("Location: index.php?edit_success=1");
exit();
