<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u925878138_tripplex;charset=utf8mb4",
        "u925878138_admin",
        "Chills@1008!!"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ DB Connected!";
} catch (Throwable $e) {
    echo "❌ DB Failed: " . $e->getMessage();
}

