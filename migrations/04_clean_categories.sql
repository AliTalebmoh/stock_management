-- Drop migrations table to start fresh
DROP TABLE IF EXISTS migrations;

-- Create migrations table
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clean up products table
SET @exist := (SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'stock_management'
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'category_type');

SET @sql := IF(@exist > 0, 'ALTER TABLE products DROP COLUMN category_type', 'SELECT "Column does not exist"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing columns
ALTER TABLE products
    MODIFY COLUMN category VARCHAR(100) NOT NULL DEFAULT 'Non classé',
    MODIFY COLUMN subcategory VARCHAR(100) DEFAULT NULL;

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
    'Matériel Électrique',
    'Matériel de Sport',
    'Matériel Médical',
    'Matériel d''Artisanat',
    'Matériel d''Entretien',
    'Matériel de Coiffure',
    'Matériel de Plomberie',
    'Matériel de Bricolage',
    'Produits Chimiques',
    'Non classé'
) NOT NULL DEFAULT 'Non classé';

-- Set character set and collation
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = utf8mb4_unicode_ci;

-- Update existing categories to match new structure
UPDATE products SET category_type = 
CASE
    -- Matériel Électrique
    WHEN LOWER(designation) LIKE '%ampoule%' OR LOWER(designation) LIKE '%douille%' 
    OR LOWER(designation) LIKE '%interipteur%' OR LOWER(designation) LIKE '%fil electrique%'
    OR LOWER(designation) LIKE '%fusible%' OR LOWER(designation) LIKE '%contacteur%'
    OR LOWER(designation) LIKE '%bouton poussoir%' OR LOWER(designation) LIKE '%va & vient%'
    OR LOWER(designation) LIKE '%lighting%' OR LOWER(designation) LIKE '%sonnette%' THEN 'Matériel Électrique'

    -- Matériel de Sport
    WHEN LOWER(designation) LIKE '%ballon%' OR LOWER(designation) LIKE '%basket%'
    OR LOWER(designation) LIKE '%foot%' OR LOWER(designation) LIKE '%volley%'
    OR LOWER(designation) LIKE '%coupe du tournoi%' THEN 'Matériel de Sport'

    -- Matériel Médical
    WHEN LOWER(designation) LIKE '%chaise%médicale%' OR LOWER(designation) LIKE '%déambulateur%'
    OR LOWER(designation) LIKE '%solution hydroalcolique%' OR LOWER(designation) LIKE '%désinfectant%'
    OR LOWER(designation) LIKE '%gant%' OR LOWER(designation) LIKE '%gel%' OR LOWER(designation) LIKE '%bavette%'
    OR LOWER(designation) LIKE '%javel%' THEN 'Matériel Médical'

    -- Matériel d'Artisanat
    WHEN LOWER(designation) LIKE '%machine à coudre%' OR LOWER(designation) LIKE '%fil de trame%'
    OR LOWER(designation) LIKE '%fils de chaine%' OR LOWER(designation) LIKE '%aiguille%'
    OR LOWER(designation) LIKE '%bendir%' OR LOWER(designation) LIKE '%tam tam%'
    OR LOWER(designation) LIKE '%babouche%' OR LOWER(designation) LIKE '%brade%'
    OR LOWER(designation) LIKE '%coffret en bois%' OR LOWER(designation) LIKE '%coupe & couture%'
    OR LOWER(designation) LIKE '%goulla%' OR LOWER(designation) LIKE '%kafton%'
    OR LOWER(designation) LIKE '%karchale%' THEN 'Matériel d''Artisanat'

    -- Matériel d'Entretien
    WHEN LOWER(designation) LIKE '%balai%' OR LOWER(designation) LIKE '%arrosoir%'
    OR LOWER(designation) LIKE '%savon%' OR LOWER(designation) LIKE '%nettoyage%'
    OR LOWER(designation) LIKE '%poubelle%' THEN 'Matériel d''Entretien'

    -- Matériel de Coiffure
    WHEN LOWER(designation) LIKE '%coiffure%' OR LOWER(designation) LIKE '%coloration%'
    OR LOWER(designation) LIKE '%mèche%' OR LOWER(designation) LIKE '%casque%'
    OR LOWER(designation) LIKE '%bol%coloration%' THEN 'Matériel de Coiffure'

    -- Matériel de Plomberie
    WHEN LOWER(designation) LIKE '%coude%' OR LOWER(designation) LIKE '%flexible%'
    OR LOWER(designation) LIKE '%plomberie%' OR LOWER(designation) LIKE '%robinet%'
    OR LOWER(designation) LIKE '%joint%' THEN 'Matériel de Plomberie'

    -- Matériel de Bricolage
    WHEN LOWER(designation) LIKE '%clé%' OR LOWER(designation) LIKE '%cheville%'
    OR LOWER(designation) LIKE '%boitier%' OR LOWER(designation) LIKE '%vis%'
    OR LOWER(designation) LIKE '%outil%' OR LOWER(designation) LIKE '%gratoir%' THEN 'Matériel de Bricolage'

    -- Produits Chimiques
    WHEN LOWER(designation) LIKE '%acide%' OR LOWER(designation) LIKE '%chlor%'
    OR LOWER(designation) LIKE '%solution%' OR LOWER(designation) LIKE '%chimique%' THEN 'Produits Chimiques'

    -- Existing categories 
    WHEN LOWER(designation) LIKE '%agrafe%' OR LOWER(designation) LIKE '%trombone%' OR LOWER(designation) LIKE '%ciseau%' 
    OR LOWER(designation) LIKE '%colle%' OR LOWER(designation) LIKE '%post-it%' OR LOWER(designation) LIKE '%scotch%' 
    OR LOWER(designation) LIKE '%dateur%' OR LOWER(designation) LIKE '%calculatrice%' OR LOWER(designation) LIKE '%cutter%'
    OR LOWER(designation) LIKE '%destructeur%' OR LOWER(designation) LIKE '%tampon%' THEN 'Fournitures de Bureau'
    
    WHEN LOWER(designation) LIKE '%cahier%' OR LOWER(designation) LIKE '%bloc note%' OR LOWER(designation) LIKE '%registre%' 
    OR LOWER(designation) LIKE '%enveloppe%' OR LOWER(designation) LIKE '%chemise%' OR LOWER(designation) LIKE '%carnet%'
    OR LOWER(designation) LIKE '%couverture%' OR LOWER(designation) LIKE '%papier%' OR LOWER(designation) LIKE '%etiquette%'
    THEN 'Papeterie'
    
    WHEN LOWER(designation) LIKE '%stylo%' OR LOWER(designation) LIKE '%crayon%' OR LOWER(designation) LIKE '%marqueur%' 
    OR LOWER(designation) LIKE '%gomme%' OR LOWER(designation) LIKE '%taille crayon%' OR LOWER(designation) LIKE '%fluorescent%'
    OR LOWER(designation) LIKE '%encre%' THEN 'Matériel d''Écriture'
    
    WHEN LOWER(designation) LIKE '%compas%' OR LOWER(designation) LIKE '%règle%' OR LOWER(designation) LIKE '%equerre%' 
    OR LOWER(designation) LIKE '%rapporteur%' OR LOWER(designation) LIKE '%dessin%' THEN 'Matériel de Dessin'
    
    WHEN LOWER(designation) LIKE '%ordinateur%' OR LOWER(designation) LIKE '%clavier%' OR LOWER(designation) LIKE '%souris%' 
    OR LOWER(designation) LIKE '%usb%' OR LOWER(designation) LIKE '%câble%' OR LOWER(designation) LIKE '%dell%'
    OR LOWER(designation) LIKE '%computer%' OR LOWER(designation) LIKE '%fil hd%' THEN 'Matériel Informatique'
    
    WHEN LOWER(designation) LIKE '%toner%' OR LOWER(designation) LIKE '%cartouche%' OR LOWER(designation) LIKE '%rame papier%' 
    OR LOWER(designation) LIKE '%cd%' OR LOWER(designation) LIKE '%dvd%' THEN 'Consommables d''Impression'
    
    WHEN LOWER(designation) LIKE '%ardoise%' OR LOWER(designation) LIKE '%craie%' OR LOWER(designation) LIKE '%tableau%' 
    OR LOWER(designation) LIKE '%brosse%' OR LOWER(designation) LIKE '%flip chart%' OR LOWER(designation) LIKE '%cartable%'
    OR LOWER(designation) LIKE '%trousse%' OR LOWER(designation) LIKE '%livre%' OR LOWER(designation) LIKE '%education%'
    OR LOWER(designation) LIKE '%scolaire%' OR LOWER(designation) LIKE '%consonne%' THEN 'Matériel Pédagogique'
    
    WHEN LOWER(designation) LIKE '%classeur%' OR LOWER(designation) LIKE '%archive%' OR LOWER(designation) LIKE '%boite%' 
    OR LOWER(designation) LIKE '%pochette%' OR LOWER(designation) LIKE '%rangement%' THEN 'Matériel de Rangement'
    
    WHEN LOWER(designation) LIKE '%peinture%' OR LOWER(designation) LIKE '%pinceau%' OR LOWER(designation) LIKE '%crepon%' 
    OR LOWER(designation) LIKE '%feutre%' OR LOWER(designation) LIKE '%couleur%' OR LOWER(designation) LIKE '%album%'
    OR LOWER(designation) LIKE '%photo%' THEN 'Matériel Artistique'
    
    WHEN LOWER(designation) LIKE '%magnétique%' OR LOWER(designation) LIKE '%porte%' OR LOWER(designation) LIKE '%badge%' 
    OR LOWER(designation) LIKE '%drapeau%' OR LOWER(designation) LIKE '%affichage%' THEN 'Matériel de Présentation'
    
    ELSE 'Non classé'
END;

-- Create an index on category_type for better performance
SET @exist := (SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'stock_management'
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_category_type');

SET @sql := IF(@exist > 0, 'DROP INDEX idx_category_type ON products', 'SELECT "Index does not exist"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE INDEX idx_category_type ON products(category_type);
