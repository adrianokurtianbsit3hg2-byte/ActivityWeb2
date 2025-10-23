<?php
include("config.php");
include("firebaseRDB.php");

$db = new firebaseRDB($databaseURL);
$id = $_GET['id'];
if($id != ""){
   $delete = $db->delete("faculty", $id);
   header("Location: index.php?delete_success=1");
   exit();
}
