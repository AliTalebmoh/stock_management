-- Drop existing category_type column if it exists
SET @exist := (SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'stock_management'
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'category_type');

SET @sql := IF(@exist > 0, 'ALTER TABLE products DROP COLUMN category_type', 'SELECT \"Column does not exist\"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add new category_type column
ALTER TABLE products
ADD COLUMN category_type ENUM(
    'Fournitures de Bureau',
    'Papeterie',
    'Matériel d''Écriture',
    'Matériel de Dessin',
    'Matériel Informatique',
    'Consommables d''Impression',
    'Matériel Pédagogique',
    'Matériel de Rangement',
    'Matériel Artistique',
    'Matériel de Présentation',
    'Non classé'
) NOT NULL DEFAULT 'Non classé';

-- Update existing categories to match new structure
UPDATE products SET category_type = 
CASE 
    WHEN LOWER(designation) LIKE '%agrafe%' OR LOWER(designation) LIKE '%trombone%' OR LOWER(designation) LIKE '%ciseau%' 
    OR LOWER(designation) LIKE '%colle%' OR LOWER(designation) LIKE '%post-it%' OR LOWER(designation) LIKE '%scotch%' 
    OR LOWER(designation) LIKE '%dateur%' THEN 'Fournitures de Bureau'
    
    WHEN LOWER(designation) LIKE '%cahier%' OR LOWER(designation) LIKE '%bloc note%' OR LOWER(designation) LIKE '%registre%' 
    OR LOWER(designation) LIKE '%enveloppe%' OR LOWER(designation) LIKE '%chemise%' THEN 'Papeterie'
    
    WHEN LOWER(designation) LIKE '%stylo%' OR LOWER(designation) LIKE '%crayon%' OR LOWER(designation) LIKE '%marqueur%' 
    OR LOWER(designation) LIKE '%gomme%' OR LOWER(designation) LIKE '%taille crayon%' THEN 'Matériel d''Écriture'
    
    WHEN LOWER(designation) LIKE '%compas%' OR LOWER(designation) LIKE '%règle%' OR LOWER(designation) LIKE '%equerre%' 
    OR LOWER(designation) LIKE '%rapporteur%' OR LOWER(designation) LIKE '%dessin%' THEN 'Matériel de Dessin'
    
    WHEN LOWER(designation) LIKE '%ordinateur%' OR LOWER(designation) LIKE '%clavier%' OR LOWER(designation) LIKE '%souris%' 
    OR LOWER(designation) LIKE '%usb%' OR LOWER(designation) LIKE '%câble%' OR LOWER(designation) LIKE '%dell%' THEN 'Matériel Informatique'
    
    WHEN LOWER(designation) LIKE '%toner%' OR LOWER(designation) LIKE '%cartouche%' OR LOWER(designation) LIKE '%rame papier%' 
    OR LOWER(designation) LIKE '%cd%' OR LOWER(designation) LIKE '%dvd%' THEN 'Consommables d''Impression'
    
    WHEN LOWER(designation) LIKE '%ardoise%' OR LOWER(designation) LIKE '%craie%' OR LOWER(designation) LIKE '%tableau%' 
    OR LOWER(designation) LIKE '%brosse%' OR LOWER(designation) LIKE '%flip chart%' THEN 'Matériel Pédagogique'
    
    WHEN LOWER(designation) LIKE '%classeur%' OR LOWER(designation) LIKE '%archive%' OR LOWER(designation) LIKE '%boite%' 
    OR LOWER(designation) LIKE '%pochette%' THEN 'Matériel de Rangement'
    
    WHEN LOWER(designation) LIKE '%peinture%' OR LOWER(designation) LIKE '%pinceau%' OR LOWER(designation) LIKE '%crepon%' 
    OR LOWER(designation) LIKE '%feutre%' OR LOWER(designation) LIKE '%couleur%' THEN 'Matériel Artistique'
    
    WHEN LOWER(designation) LIKE '%magnétique%' OR LOWER(designation) LIKE '%porte%' OR LOWER(designation) LIKE '%badge%' 
    OR LOWER(designation) LIKE '%drapeau%' THEN 'Matériel de Présentation'
    
    ELSE 'Non classé'
END;

-- Create an index on category_type for better performance
CREATE INDEX idx_category_type ON products(category_type);
