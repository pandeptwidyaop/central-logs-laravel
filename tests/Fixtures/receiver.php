<?php

/*
 * Minimal stand-in for the Central Logs API, served by `php -S` during tests.
 * Every request is appended to the file named by CENTRAL_LOGS_RECEIVER_FILE.
 */
file_put_contents(
    getenv('CENTRAL_LOGS_RECEIVER_FILE'),
    json_encode([
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
        'api_key' => $_SERVER['HTTP_X_API_KEY'] ?? null,
        'body' => json_decode(file_get_contents('php://input'), true),
    ]).PHP_EOL,
    FILE_APPEND
);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
