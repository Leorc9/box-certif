<?php

$host = 'localhost';
$dbname = 'boxdb';
$username = 'boxdbuser';
$password = 'boxdbpassword';

// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}
