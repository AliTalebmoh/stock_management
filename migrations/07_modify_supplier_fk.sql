-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS=0;

-- Drop the existing foreign key constraint
ALTER TABLE stock_entries 
DROP FOREIGN KEY stock_entries_ibfk_2;

-- Add the new foreign key constraint that allows NULL and SET NULL on delete
ALTER TABLE stock_entries
MODIFY COLUMN supplier_id INT NULL,
ADD CONSTRAINT stock_entries_ibfk_2 
FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
ON DELETE SET NULL;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;