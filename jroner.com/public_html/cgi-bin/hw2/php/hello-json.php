<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json; charset=utf-8");

$payload = [
  "title"   => "Hello, PHP! From Jacob Roner",
  "heading" => "Hello, PHP! From Jacob Roner",
  "message" => "This page was generated with the PHP programming language from jroner.com",
  "time"    => date("Y-m-d H:i:s"),
  "ip"      => $_SERVER["REMOTE_ADDR"] ?? ""
];

echo json_encode($payload, JSON_PRETTY_PRINT);
