<?php
require_once 'db.php';
commonHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];
$mysqli = getDb();

switch ($method) {

    // ── GET / or GET /?id=N ────────────────────────────────────────────────
    case 'GET':
        if ($id !== null) {
            $stmt = $mysqli->prepare("SELECT * FROM `static` WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                echo json_encode(["ok" => true, "data" => $row]);
            } else {
                http_response_code(404);
                echo json_encode(["ok" => false, "error" => "Not found"]);
            }
        } else {
            $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $stmt = $mysqli->prepare("SELECT * FROM `static` ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["ok" => true, "data" => $rows, "count" => count($rows)]);
        }
        break;

    // ── POST / ─────────────────────────────────────────────────────────────
    case 'POST':
        $d = getRequestBody();

        $sessionId        = $d['session_id']          ?? null;
        $eventTimestamp   = $d['event_timestamp']      ?? null;
        $page             = $d['page']                 ?? null;
        $sourceIp         = $d['source_ip']            ?? $_SERVER['REMOTE_ADDR'];
        $userAgent        = $d['request_user_agent']   ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $payloadUserAgent = $d['payload_user_agent']   ?? null;
        $language         = $d['language']             ?? null;
        $cookiesEnabled   = array_key_exists('cookies_enabled',    $d) ? (int)(bool)$d['cookies_enabled']    : null;
        $jsEnabled        = array_key_exists('javascript_enabled', $d) ? (int)(bool)$d['javascript_enabled'] : null;
        $imagesEnabled    = array_key_exists('images_enabled',     $d) ? (int)(bool)$d['images_enabled']     : null;
        $cssEnabled       = array_key_exists('css_enabled',        $d) ? (int)(bool)$d['css_enabled']        : null;
        $screen           = isset($d['screen'])      ? json_encode($d['screen'])      : null;
        $windowSize       = isset($d['window_size']) ? json_encode($d['window_size']) : null;
        $network          = isset($d['network'])     ? json_encode($d['network'])     : null;
        $jsonForDb        = json_encode($d);

        $stmt = $mysqli->prepare("INSERT INTO `static`
            (session_id, event_timestamp, page, source_ip, request_user_agent,
             payload_user_agent, language, cookies_enabled, javascript_enabled,
             images_enabled, css_enabled, screen, window_size, network, payload_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssiiiissss",
            $sessionId, $eventTimestamp, $page, $sourceIp, $userAgent,
            $payloadUserAgent, $language, $cookiesEnabled, $jsEnabled,
            $imagesEnabled, $cssEnabled, $screen, $windowSize, $network, $jsonForDb);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["ok" => true, "id" => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["ok" => false, "error" => "Insert failed"]);
        }
        break;

    // ── PUT /?id=N ─────────────────────────────────────────────────────────
    case 'PUT':
        $id = requireId();
        $d  = getRequestBody();

        $allowed = [
            'session_id', 'event_timestamp', 'page', 'source_ip',
            'request_user_agent', 'payload_user_agent', 'language',
            'cookies_enabled', 'javascript_enabled', 'images_enabled',
            'css_enabled', 'screen', 'window_size', 'network'
        ];
        $sets   = [];
        $params = [];
        $types  = '';

        foreach ($allowed as $col) {
            if (array_key_exists($col, $d)) {
                $sets[]   = "`$col` = ?";
                $params[] = is_array($d[$col]) ? json_encode($d[$col]) : $d[$col];
                $types   .= 's';
            }
        }

        if (empty($sets)) {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "No valid fields to update"]);
            break;
        }

        $params[] = $id;
        $types   .= 'i';

        $stmt = $mysqli->prepare("UPDATE `static` SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo json_encode(["ok" => false, "error" => "Not found"]);
            } else {
                echo json_encode(["ok" => true, "id" => $id]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["ok" => false, "error" => "Update failed"]);
        }
        break;

    // ── DELETE /?id=N ──────────────────────────────────────────────────────
    case 'DELETE':
        $id   = requireId();
        $stmt = $mysqli->prepare("DELETE FROM `static` WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo json_encode(["ok" => false, "error" => "Not found"]);
            } else {
                echo json_encode(["ok" => true, "id" => $id]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["ok" => false, "error" => "Delete failed"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["ok" => false, "error" => "Method not allowed"]);
}

$mysqli->close();
