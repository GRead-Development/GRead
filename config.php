<?php
// config.php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'daniel');
define('DB_PASSWORD', 'password'); // <-- IMPORTANT: Use the password you created
define('DB_NAME', 'gread');

// Attempt to connect to the database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli === false) {
    die("ERROR: Could not connect. " . $mysqli->connect_error);
}

// Start the session for user login management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
