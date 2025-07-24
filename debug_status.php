<?php
// Debug endpoint for checking database connection and login system status
header('Content-Type: application/json');

// Only allow in development or for debugging
if (!isset($_GET['debug']) || $_GET['debug'] !== 'true') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
    ],
    'config_status' => [],
    'database_status' => []
];

try {
    // Check config.php
    require_once 'config.php';
    $debug_info['config_status']['config.php'] = [
        'loaded' => true,
        'db_host' => defined('DB_HOST') ? DB_HOST : 'not defined',
        'db_name' => defined('DB_NAME') ? DB_NAME : 'not defined',
        'db_user' => defined('DB_USER') ? DB_USER : 'not defined',
        'db_pass_set' => defined('DB_PASS') && !empty(DB_PASS)
    ];
    
    // Test database connection
    try {
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            if ($mysqli->connect_errno) {
                $debug_info['database_status'] = [
                    'connected' => false,
                    'error' => $mysqli->connect_error,
                    'errno' => $mysqli->connect_errno
                ];
            } else {
                $debug_info['database_status'] = [
                    'connected' => true,
                    'server_info' => $mysqli->server_info,
                    'client_info' => $mysqli->client_info,
                    'charset' => $mysqli->character_set_name()
                ];
                
                // Test a simple query
                $result = $mysqli->query("SELECT 1 as test");
                $debug_info['database_status']['query_test'] = $result ? 'success' : 'failed';
            }
        } else {
            $debug_info['database_status'] = [
                'connected' => false,
                'error' => 'mysqli object not available'
            ];
        }
    } catch (Exception $e) {
        $debug_info['database_status'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
    
} catch (Exception $e) {
    $debug_info['config_status']['error'] = $e->getMessage();
}

// Check session status
$debug_info['session_status'] = [
    'active' => session_status() === PHP_SESSION_ACTIVE,
    'id' => session_id(),
    'logged_in' => isset($_SESSION['logged_in']) ? $_SESSION['logged_in'] : false
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>