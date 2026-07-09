<?php

// $host = "localhost";
// $user = "root";
// $password = "ak25082003?";

// $host = "sql12.freesqldatabase.com";
// $user = "sql12832584";
// $password = "R6HEDR46km";


// $conn = mysqli_connect($host,$user,$password);

// if(!$conn){
//     die("Connection Failed");
// }

// $sql ="CREATE DATABASE IF NOT EXISTS tracker";

// mysqli_query($conn,$sql);

// mysqli_select_db($conn,"tracker");


$host = "sql12.freesqldatabase.com";
$user = "sql12832584";
$password = "R6HEDR46km";
$database = "sql12832584";

$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database
);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

echo "Connected Successfully";



$sql ="CREATE TABLE IF NOT EXISTS device_mapping(
id INT AUTO_INCREMENT PRIMARY KEY,
tag_id VARCHAR(20) UNIQUE,
device_name VARCHAR(100)
)";
mysqli_query($conn,$sql);


$sql ="CREATE TABLE IF NOT EXISTS antenna_mapping (
id INT AUTO_INCREMENT PRIMARY KEY,
antenna_id VARCHAR(20) UNIQUE,
location VARCHAR(100)
)";
mysqli_query($conn,$sql);


$sql ="CREATE TABLE IF NOT EXISTS tracker_status (
id INT AUTO_INCREMENT PRIMARY KEY,
tag_id VARCHAR(20) UNIQUE,
device_name VARCHAR(100),
current_location VARCHAR(100),
receiver VARCHAR(20),
best_rssi INT,
status VARCHAR(30),
updated_at DATETIME
)";
mysqli_query($conn,$sql);


$sql ="CREATE TABLE IF NOT EXISTS tracker_history (
id INT AUTO_INCREMENT PRIMARY KEY,
tag_id VARCHAR(20),
device_name VARCHAR(100),
location VARCHAR(100),
receiver VARCHAR(20),
entry_time DATETIME,
exit_time DATETIME NULL
)";
mysqli_query($conn,$sql);

echo "Setup Completed";

?>