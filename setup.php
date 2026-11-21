<?php
mysqli_report(MYSQLI_REPORT_OFF);



//  new db --------------------------------------------------------

$host = "ak-mysql-25-akshaypanaganti9395-9c17.i.aivencloud.com";
$user = "avnadmin";
$password = getenv("DB_PASSWORD");
$database = "defaultdb";
$port = 15642;

$conn = mysqli_init();

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    "C:/xampp/htdocs/tracker_php/certs/ca.pem",
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
    die("Connection failed: " . mysqli_connect_error());
}


// echo "Connected Successfully";



$sql ="CREATE TABLE IF NOT EXISTS device_mapping(
id INT AUTO_INCREMENT PRIMARY KEY,
asset_tag_number VARCHAR(20) UNIQUE,
device_name VARCHAR(100)
)";
mysqli_query($conn,$sql);

$result = mysqli_query($conn, "SHOW COLUMNS FROM device_mapping LIKE 'asset_tag_number'");
if($result && mysqli_num_rows($result) == 0)
{
    mysqli_query($conn, "ALTER TABLE device_mapping ADD asset_tag_number VARCHAR(20) UNIQUE AFTER id");
    mysqli_query($conn, "UPDATE device_mapping SET asset_tag_number = tag_id WHERE asset_tag_number IS NULL");
}

$sql ="CREATE TABLE IF NOT EXISTS rtls_badge(
id INT AUTO_INCREMENT PRIMARY KEY,
ble_enabled VARCHAR(10),
badge_type VARCHAR(30),
badge_id_1 VARCHAR(20) UNIQUE,
badge_id_2 VARCHAR(20) NULL,
badge_id_3 VARCHAR(20) NULL,
asset_tag_number VARCHAR(20) UNIQUE,
badge_make VARCHAR(100)
)";
mysqli_query($conn,$sql);

$sql = "INSERT INTO rtls_badge
(ble_enabled, badge_type, badge_id_1, badge_id_2, badge_id_3, asset_tag_number, badge_make)
VALUES
('yes', 'BLE', 'MD1', NULL, NULL, 'MAMO007', 'phone'),
('yes', 'BLE', 'MD2', NULL, NULL, 'SAMA001', 'phone')
ON DUPLICATE KEY UPDATE
ble_enabled = VALUES(ble_enabled),
badge_type = VALUES(badge_type),
badge_id_2 = VALUES(badge_id_2),
badge_id_3 = VALUES(badge_id_3),
asset_tag_number = VALUES(asset_tag_number),
badge_make = VALUES(badge_make)";
mysqli_query($conn,$sql);

$result = mysqli_query($conn, "SHOW COLUMNS FROM device_mapping LIKE 'tag_id'");
if($result && mysqli_num_rows($result) > 0)
{
    $sql = "UPDATE device_mapping dm
            INNER JOIN rtls_badge rb
                ON UPPER(dm.tag_id) IN (
                    UPPER(rb.badge_id_1),
                    UPPER(rb.badge_id_2),
                    UPPER(rb.badge_id_3)
                )
            SET dm.asset_tag_number = rb.asset_tag_number";
    mysqli_query($conn,$sql);

    mysqli_query($conn, "ALTER TABLE device_mapping DROP COLUMN tag_id");
}


$sql ="CREATE TABLE IF NOT EXISTS locations (
id INT AUTO_INCREMENT PRIMARY KEY,
location_id VARCHAR(30) UNIQUE,
lecode VARCHAR(30),
section_department VARCHAR(100),
region VARCHAR(100),
country VARCHAR(100),
state VARCHAR(100),
city VARCHAR(100),
room VARCHAR(100),
office_address VARCHAR(255),
created_by_role VARCHAR(100)
)";
mysqli_query($conn,$sql);

$sql = "INSERT INTO locations
(location_id, lecode, section_department, region, country, state, city, room, office_address, created_by_role)
VALUES
('LOC-HYD-002', 'DSTR', 'Accountant', 'South India', 'India', 'Telangana', 'Hyderabad', 'Room 402', 'Pratrika Nagar', 'Lavanya (Employee)'),
('LOC-MUM-001', 'STRE', 'Accountant', 'West India', 'India', 'Maharashtra', 'Mumbai', 'Room 501', 'Palak', 'DigitoWork (Super Admin)'),
('LOC-BVSP-002', 'STRE', 'Sales', 'South India', 'India', 'Telangana', 'Hyderabad', 'Room 210', 'Road No. 2, Banjara Hills', 'DigitoWork (Super Admin)')
ON DUPLICATE KEY UPDATE
lecode = VALUES(lecode),
section_department = VALUES(section_department),
region = VALUES(region),
country = VALUES(country),
state = VALUES(state),
city = VALUES(city),
room = VALUES(room),
office_address = VALUES(office_address),
created_by_role = VALUES(created_by_role)";
mysqli_query($conn,$sql);

$sql ="CREATE TABLE IF NOT EXISTS antenna_mapping (
id INT AUTO_INCREMENT PRIMARY KEY,
antenna_id VARCHAR(20) UNIQUE,
location_id VARCHAR(30)
)";
mysqli_query($conn,$sql);

$result = mysqli_query($conn, "SHOW COLUMNS FROM antenna_mapping LIKE 'location_id'");
if($result && mysqli_num_rows($result) == 0)
{
    mysqli_query($conn, "ALTER TABLE antenna_mapping ADD location_id VARCHAR(30) AFTER antenna_id");
}

$result = mysqli_query($conn, "SHOW COLUMNS FROM antenna_mapping LIKE 'location'");
if($result && mysqli_num_rows($result) > 0)
{
    mysqli_query($conn, "ALTER TABLE antenna_mapping DROP COLUMN location");
}

$sql = "INSERT INTO antenna_mapping
(antenna_id, location_id)
VALUES
('A1', 'LOC-HYD-002'),
('A2', 'LOC-MUM-001'),
('A3', 'LOC-BVSP-002')
ON DUPLICATE KEY UPDATE
location_id = VALUES(location_id)";
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

$sql = "UPDATE tracker_status ts
        INNER JOIN antenna_mapping am
            ON ts.receiver = am.antenna_id
        INNER JOIN locations l
            ON am.location_id = l.location_id
        SET ts.current_location = CONCAT(l.section_department, ' - ', l.room)";
mysqli_query($conn,$sql);

$sql = "UPDATE tracker_history th
        INNER JOIN antenna_mapping am
            ON th.receiver = am.antenna_id
        INNER JOIN locations l
            ON am.location_id = l.location_id
        SET th.location = CONCAT(l.section_department, ' - ', l.room)";
mysqli_query($conn,$sql);


$sql = "CREATE TABLE IF NOT EXISTS tracker_readings
(
    id INT AUTO_INCREMENT PRIMARY KEY,

    tag_id VARCHAR(20) NOT NULL,

    receiver VARCHAR(20) NOT NULL,

    rssi INT NOT NULL,

    updated_at DATETIME NOT NULL,

    UNIQUE(tag_id, receiver)
)";

mysqli_query($conn, $sql);

$sql = "CREATE TABLE IF NOT EXISTS tracker_pending (
    tag_id VARCHAR(20) PRIMARY KEY,
    candidate_receiver VARCHAR(20) NOT NULL,
    first_seen DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
)";
mysqli_query($conn, $sql);

// echo "Setup Completed";

?>
