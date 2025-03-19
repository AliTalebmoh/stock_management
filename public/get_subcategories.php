<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['category'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Category parameter is required']);
    exit;
}

$category = $_GET['category'];

try {
    $stmt = $db->prepare("
        SELECT DISTINCT subcategory 
        FROM products 
        WHERE category = :category 
            AND subcategory IS NOT NULL 
            AND subcategory != '' 
        ORDER BY subcategory
    ");
    
    $stmt->execute(['category' => $category]);
    $subcategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($subcategories);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
