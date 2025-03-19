<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

try {
    $db = Connection::getInstance();
    
    // Get all SQL files in the migrations directory
    $migrations = glob(__DIR__ . '/*.sql');
    sort($migrations); // Sort by filename
    
    foreach ($migrations as $migration) {
        echo "Running migration: " . basename($migration) . "\n";
        
        // Read and execute the SQL file
        $sql = file_get_contents($migration);
        $db->exec($sql);
        
        echo "Migration completed successfully.\n";
    }
    
    echo "\nAll migrations completed successfully!\n";
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
