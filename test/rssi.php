<?php

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    die("Send a POST request.");
}

$data = json_decode(file_get_contents("php://input"), true);

file_put_contents(
    "latest_data.json",
    json_encode($data, JSON_PRETTY_PRINT)
);

echo json_encode([
    "success" => true
]);

?>