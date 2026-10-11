<?php
$hostname = 'localhost';
$username = 'root';
$password = '';
$dbname = 'healthdb';

$conn = new mysqli($hostname, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
