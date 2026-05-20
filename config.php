<?php
/**
 * Configuration — Load environment variables and set up basic settings.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file if exists (silently skip if not)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

define('DEEPSEEK_API_KEY', $_ENV['DEEPSEEK_API_KEY'] ?? '');
define('DEEPSEEK_BASE_URL', $_ENV['DEEPSEEK_BASE_URL'] ?? 'https://api.deepseek.com/v1');
define('DB_PATH', __DIR__ . '/legal_copilot.db');

/**
 * Send a JSON response.
 */
function json_response(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an HTML response.
 */
function html_response(string $html, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}
