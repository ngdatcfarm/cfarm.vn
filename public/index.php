<?php
/**
 * public/index.php
 *
 * Entry point duy nhất của ứng dụng.
 * Tất cả request đều đi qua file này.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/app/bootstrap.php';