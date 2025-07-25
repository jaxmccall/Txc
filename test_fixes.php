#!/usr/bin/env php
<?php
/**
 * Simple test script to validate the admin login flow logic
 */

// Mock the session and database
$_SESSION = [];

function test_admin_login() {
    echo "Testing admin login flow...\n";
    
    // Test 1: Check admin_login.php syntax
    $output = shell_exec('php -l admin_login.php 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ admin_login.php syntax is valid\n";
    } else {
        echo "❌ admin_login.php has syntax errors: $output\n";
        return false;
    }
    
    // Test 2: Check admin-session-check.php syntax
    $output = shell_exec('php -l api/admin-session-check.php 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ api/admin-session-check.php syntax is valid\n";
    } else {
        echo "❌ api/admin-session-check.php has syntax errors: $output\n";
        return false;
    }
    
    // Test 3: Check admin-logout.php syntax  
    $output = shell_exec('php -l admin-logout.php 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ admin-logout.php syntax is valid\n";
    } else {
        echo "❌ admin-logout.php has syntax errors: $output\n";
        return false;
    }
    
    // Test 4: Check profile API syntax
    $output = shell_exec('php -l api/get-user-profile.php 2>&1');
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ api/get-user-profile.php syntax is valid\n";
    } else {
        echo "❌ api/get-user-profile.php has syntax errors: $output\n";
        return false;
    }
    
    return true;
}

function test_api_responses() {
    echo "\nTesting API response formats...\n";
    
    // Mock session for testing
    $_SESSION = [];
    
    // Test session check without login
    ob_start();
    include 'api/admin-session-check.php';
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    if (isset($response['authenticated']) && $response['authenticated'] === false) {
        echo "✅ admin-session-check returns correct unauthenticated response\n";
    } else {
        echo "❌ admin-session-check response format issue: $output\n";
        return false;
    }
    
    return true;
}

function test_html_files() {
    echo "\nChecking HTML files...\n";
    
    // Check if admin-login.html has the correct endpoint
    $content = file_get_contents('admin-login.html');
    if (strpos($content, "fetch('admin_login.php'") !== false) {
        echo "✅ admin-login.html uses correct endpoint\n";
    } else {
        echo "❌ admin-login.html endpoint may be incorrect\n";
        return false;
    }
    
    // Check if profile.html has real data loading
    $profileContent = file_get_contents('profile.html');
    if (strpos($profileContent, "api/get-user-profile.php") !== false) {
        echo "✅ profile.html uses real data API\n";
    } else {
        echo "❌ profile.html may still use dummy data\n";
        return false;
    }
    
    return true;
}

// Run tests
$tests_passed = 0;
$total_tests = 3;

if (test_admin_login()) {
    $tests_passed++;
    echo "\n✅ Admin login flow tests passed\n";
} else {
    echo "\n❌ Admin login flow tests failed\n";
}

if (test_api_responses()) {
    $tests_passed++;
    echo "\n✅ API response tests passed\n";
} else {
    echo "\n❌ API response tests failed\n";
}

if (test_html_files()) {
    $tests_passed++;
    echo "\n✅ HTML files tests passed\n";
} else {
    echo "\n❌ HTML files tests failed\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Results: $tests_passed/$total_tests tests passed\n";

if ($tests_passed === $total_tests) {
    echo "🎉 All tests passed! The fixes should work correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the issues above.\n";
    exit(1);
}
?>