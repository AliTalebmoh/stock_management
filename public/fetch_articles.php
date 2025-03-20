<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Get search parameters
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "
    SELECT 
        id,
        designation,
        description,
        report_stock,
        entre,
        sortie,
        current_stock,
        updated_at,
        category_type
    FROM products
    WHERE 1=1";

$params = [];

if ($category !== 'all') {
    $query .= " AND category_type = :category";
    $params['category'] = $category;
}

if ($search) {
    $query .= " AND LOWER(designation) LIKE LOWER(:search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY category_type, designation";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Group products by category
$groupedProducts = [];
foreach ($products as $product) {
    $category = $product['category_type'] ?: 'Non catégorisé';
    if (!isset($groupedProducts[$category])) {
        $groupedProducts[$category] = [];
    }
    $groupedProducts[$category][] = $product;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($groupedProducts); 