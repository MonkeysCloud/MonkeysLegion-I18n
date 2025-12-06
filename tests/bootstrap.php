<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 * 
 * This file is loaded before running tests.
 * Sets up autoloading and test environment.
 */

// Load Composer autoloader
$autoloader = require __DIR__ . '/../vendor/autoload.php';

// Set timezone
date_default_timezone_set('UTC');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
define('TESTS_DIR', __DIR__);
define('FIXTURES_DIR', __DIR__ . '/fixtures');
define('TEMP_DIR', __DIR__ . '/tmp');

// Create temp directory if it doesn't exist
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}

// Register cleanup handler
register_shutdown_function(function () {
    // Clean up temp files after tests
    if (is_dir(TEMP_DIR)) {
        $files = glob(TEMP_DIR . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
});
