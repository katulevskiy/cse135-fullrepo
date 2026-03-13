<?php
function getDb(): mysqli {
    $mysqli = new mysqli("localhost", "root", "devtheWorld#135cse", "collector_logs");
    if ($mysqli->connect_errno) {
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "Database connection failed"]);
        exit;
    }
    return $mysqli;
}

function commonHeaders(): void {
    header("Access-Control-Allow-Origin: https://test.jroner.com");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Content-Type: application/json");
}

function getRequestBody(): array {
    $rawBody = file_get_contents("php://input");
    if ($rawBody === false || $rawBody === '') {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Empty request body"]);
        exit;
    }
    $decoded = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Invalid JSON body"]);
        exit;
    }
    return $decoded;
}

function requireId(): int {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "ID required"]);
        exit;
    }
    return (int)$_GET['id'];
}
