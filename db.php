<?php
// Change these 4 values to match your XAMPP setup
// For most students using default XAMPP, you only need to change $database

$host     = "localhost";       // always localhost for XAMPP
$username = "root";            // default XAMPP username, don't change
$password = "";                // default XAMPP password is blank, don't change
$database = "workspace_pro";   // must match the database name you created in phpMyAdmin

// This connects to the database — don't touch anything below this line
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
