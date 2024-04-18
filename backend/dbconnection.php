<?php
$dsn = "mysql:host=localhost:3308;dbname=pixel";
$dbusername = "root";
$dbpass = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If there's an error, create a JSON response with the error message
    $response = [
        'success' => false,
        'message' => 'Database connection error: ' . $e->getMessage()
    ];
    // Return the JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    // Exit the script
    exit();
}
