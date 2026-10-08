<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'u810646136_crmuser');
define('DB_PASS', 'Watnii..11');
define('DB_NAME', 'u810646136_crm');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Critical Failure: " . $e->getMessage());
}
?>