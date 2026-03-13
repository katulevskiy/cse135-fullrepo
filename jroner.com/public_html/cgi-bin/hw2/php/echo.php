<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "";
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";
$userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
$ip = $_SERVER["REMOTE_ADDR"] ?? "";
$host = gethostname();
$time = date("Y-m-d H:i:s");

$data = [];
$rawBody = "";

// GET: use query params
if ($method === "GET") {
    $data = $_GET;
} else {
    // For POST/PUT/DELETE: read body
    $rawBody = file_get_contents("php://input");

    if (stripos($contentType, "application/json") !== false) {
        $decoded = json_decode($rawBody, true);
        $data = is_array($decoded) ? $decoded : ["raw_body" => $rawBody];
    } else {
        // urlencoded (or unknown): try parse
        parse_str($rawBody, $parsed);
        $data = $parsed;

        // also merge normal POST for typical POST submissions
        if ($method === "POST" && !empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
    }
}

$response = [
  "method" => $method,
  "content_type" => $contentType,
  "data_received" => $data,
  "raw_body" => $rawBody,
  "server_hostname" => $host,
  "server_time" => $time,
  "user_agent" => $userAgent,
  "client_ip" => $ip
];

echo json_encode($response, JSON_PRETTY_PRINT);
