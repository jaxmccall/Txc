<?php
// Local testing configuration - DO NOT commit to production
$DB_HOST = 'localhost';
$DB_USER = 'u925878138_admin';
$DB_PASS = 'Chills@1008!!';
$DB_NAME = 'u925878138_tripplex';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // For local testing, create a mock database interface
    $pdo = null;
    error_log("Database connection failed: " . $e->getMessage());
}
?>