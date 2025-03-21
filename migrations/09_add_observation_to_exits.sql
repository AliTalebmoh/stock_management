-- Add observation column to stock_exits table
SET @exist := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'stock_management'
    AND TABLE_NAME = 'stock_exits'
    AND COLUMN_NAME = 'observation'
);

SET @query = IF(@exist = 0, 
    'ALTER TABLE stock_exits ADD COLUMN observation TEXT NULL AFTER bon_number',
    'SELECT "Column already exists" AS message'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 