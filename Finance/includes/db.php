<?php
require_once __DIR__ . '/../config.php';

function db_connect(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);

    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Ensure UTF-8
    $conn->set_charset('utf8mb4');

    return $conn;
}

