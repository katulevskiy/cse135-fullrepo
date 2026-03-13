<?php
function getDb(): mysqli {
    static $instance = null;
    if ($instance === null) {
        $instance = new mysqli("localhost", "root", "devtheWorld#135cse", "collector_logs");
        if ($instance->connect_errno) {
            http_response_code(500);
            die(json_encode(["ok" => false, "error" => "Database connection failed"]));
        }
        $instance->set_charset("utf8mb4");
    }
    return $instance;
}
