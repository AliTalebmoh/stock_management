<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Clear existing products
try {
    // First, disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Clear related tables
    $db->exec("TRUNCATE TABLE stock_exit_items");
    $db->exec("TRUNCATE TABLE stock_exits");
    $db->exec("TRUNCATE TABLE stock_entries");
    $db->exec("TRUNCATE TABLE products");
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo "✓ Cleared existing products and related data\n";
} catch (PDOException $e) {
    die("Error clearing data: " . $e->getMessage() . "\n");
}

// Read and parse CSV file
$csvFile = isset($argv[1]) ? $argv[1] : 'Liste d\'articles.csv';
$file = fopen(__DIR__ . '/assets/' . $csvFile, 'r');
if (!$file) {
    die("Error: Could not open CSV file\n");
}

// Define category mappings
$categoryMappings = [
    'Fournitures de Bureau' => ['agrafe', 'trombone', 'ciseau', 'colle', 'post-it', 'scotch', 'dateur'],
    'Papeterie' => ['cahier', 'bloc note', 'registre', 'enveloppe', 'chemise'],
    'Matériel d\'Écriture' => ['stylo', 'crayon', 'marqueur', 'gomme', 'taille crayon'],
    'Matériel de Dessin' => ['compas', 'règle', 'equerre', 'rapporteur', 'dessin'],
    'Matériel Informatique' => ['ordinateur', 'clavier', 'souris', 'usb', 'câble', 'dell'],
    'Consommables d\'Impression' => ['toner', 'cartouche', 'rame papier', 'cd', 'dvd'],
    'Matériel Pédagogique' => ['ardoise', 'craie', 'tableau', 'brosse', 'flip chart'],
    'Matériel de Rangement' => ['classeur', 'archive', 'boite', 'pochette'],
    'Matériel Artistique' => ['peinture', 'pinceau', 'crepon', 'feutre', 'couleur'],
    'Matériel de Présentation' => ['magnétique', 'porte', 'badge', 'drapeau']
];

// Skip empty lines and headers
$row = 0;
$currentCategory = '';
$currentSubcategory = '';
$inSection = false;
while (($data = fgetcsv($file)) !== false) {
    $row++;
    
    // Skip empty rows
    if (empty($data[0]) && empty($data[1]) && empty($data[2]) && empty($data[3])) {
        continue;
    }
    
    // Check if this is a category header
    if (strpos(trim($data[0]), 'Section :') === 0) {
        $currentCategory = trim(str_replace('Section :', '', $data[0]));
        $currentSubcategory = ''; // Reset subcategory when category changes
        $inSection = true;
        continue;
    }

    // Check if this is a subcategory header
    if (strpos(trim($data[0]), 'Sous-section :') === 0) {
        $currentSubcategory = trim(str_replace('Sous-section :', '', $data[0]));
        continue;
    }

    // If we're not in a section and find a non-empty line that could be a category
    if (!$inSection && !empty(trim($data[0])) && empty($data[1]) && empty($data[2]) && empty($data[3])) {
        $currentCategory = trim($data[0]);
        $currentSubcategory = ''; // Reset subcategory when category changes
        $inSection = true;
        continue;
    }
    
    // Skip header row
    if (in_array($data[0], ['Désignation', ''])) {
        continue;
    }
    
    // Process article
    $designation = trim($data[0]);
    if (!empty($designation)) {
        // Determine category based on item description
        $itemDescription = strtolower($designation);
        $assignedCategory = '';
        
        foreach ($categoryMappings as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($itemDescription, strtolower($keyword)) !== false) {
                    $assignedCategory = $category;
                    break 2;
                }
            }
        }
        
        // If no category was found, use the current section category or default
        if (empty($assignedCategory)) {
            $assignedCategory = $currentCategory ?: 'Non classé';
        }
        
        // Update the current category
        $currentCategory = $assignedCategory;
        try {
            $stmt = $db->prepare("
                INSERT INTO products 
                (designation, description, report_stock, entre, sortie, current_stock, category, subcategory) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $report = intval($data[1]);
            $entre = intval($data[2]);
            $sortie = intval($data[3]);
            $stock = intval($data[4]);
            
            // Validate numbers
            $report = max(0, intval($data[1]));
            $entre = max(0, intval($data[2]));
            $sortie = max(0, intval($data[3]));
            $stock = max(0, intval($data[4])); // Ensure no negative stock
            
            // If stock is empty or invalid, calculate it
            if (empty($stock) || $stock < 0) {
                $stock = $report + $entre - $sortie;
                $stock = max(0, $stock); // Ensure no negative stock
            }
            
            $stmt->execute([
                $designation,
                '', // Empty description
                $report,
                $entre,
                $sortie,
                $stock,
                $currentCategory,
                $currentSubcategory
            ]);
            
            echo "✓ Added: {$designation}\n";
        } catch (PDOException $e) {
            echo "✗ Error adding {$designation}: " . $e->getMessage() . "\n";
        }
    }
}

fclose($file);
echo "\nImport completed!\n";
