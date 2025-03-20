-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS=0;

-- Drop the existing foreign key constraint
ALTER TABLE stock_exits 
DROP FOREIGN KEY stock_exits_ibfk_1;

-- Add the new foreign key constraint that allows NULL and SET NULL on delete
ALTER TABLE stock_exits
MODIFY COLUMN demander_id INT NULL,
ADD CONSTRAINT stock_exits_ibfk_1 
FOREIGN KEY (demander_id) REFERENCES demanders(id)
ON DELETE SET NULL;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1; 