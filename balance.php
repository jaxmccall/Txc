<?php
/**
 * Balance Management API - Tripple Exchange
 * Root level balance endpoint for compatibility
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Redirect to the main balance API
require_once 'api/balance.php';
?>
