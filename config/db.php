<?php
mysqli_report(MYSQLI_REPORT_OFF);

// avian --------------------------------------------------------

$host = "ak-mysql-25-akshaypanaganti9395-9c17.i.aivencloud.com";
$user = "avnadmin";
$password = getenv("DB_PASSWORD");
$database = "defaultdb";
$port = 15642;

$conn = mysqli_init();

$caCert = file_exists(__DIR__ . "/../certs/ca.pem")
    ? realpath(__DIR__ . "/../certs/ca.pem")
    : (file_exists("/var/www/html/certs/ca.pem") ? "/var/www/html/certs/ca.pem" : "C:/xampp/htdocs/tracker-from-git/certs/ca.pem");

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    $caCert,
    NULL,
    NULL
);

$connected = @mysqli_real_connect(
    $conn,
    $host,
    $user,
    $password,
    $database,
    $port,
    NULL,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    die("Connection Failed: " . mysqli_connect_error());
}

date_default_timezone_set("Asia/Kolkata");
mysqli_query($conn, "SET time_zone = '+05:30'");

?>
