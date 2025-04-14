<?php
$servername = "localhost";
$username = "Riju";
$password = "Riju@123456";
$dbname = "citadel";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
