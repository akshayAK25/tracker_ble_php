<?php

// $host = "localhost";
// $user = "root";
// $password = "ak25082003?";
// $database = "tracker";


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

date_default_timezone_set("Asia/Kolkata");
mysqli_query($conn, "SET time_zone = '+05:30'");

if (!$conn) {

    die(
        "Connection Failed : "
        . mysqli_connect_error()
    );

}

// echo "Database Connected";

?>