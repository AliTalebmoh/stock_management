-- Create database if not exists
CREATE DATABASE IF NOT EXISTS stock_management;
USE stock_management;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    designation VARCHAR(255) NOT NULL,
    description TEXT,
    report_stock INT DEFAULT 0 COMMENT 'Stock from last year',
    entre INT DEFAULT 0 COMMENT 'Total additions',
    sortie INT DEFAULT 0 COMMENT 'Total removals',
    current_stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demanders table
CREATE TABLE IF NOT EXISTS demanders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255),
    contact VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stock entries table
CREATE TABLE IF NOT EXISTS stock_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    quantity INT NOT NULL,
    entry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- Stock exits table
CREATE TABLE IF NOT EXISTS stock_exits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bon_number VARCHAR(50) NOT NULL,
    exit_date DATE NOT NULL,
    demander_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demander_id) REFERENCES demanders(id)
);

-- Stock exit items table
CREATE TABLE IF NOT EXISTS stock_exit_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    utilisation TEXT,
    FOREIGN KEY (exit_id) REFERENCES stock_exits(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Triggers to update product stock
DELIMITER //

CREATE TRIGGER after_stock_entry
AFTER INSERT ON stock_entries
FOR EACH ROW
BEGIN
    UPDATE products 
    SET 
        entre = entre + NEW.quantity,
        current_stock = current_stock + NEW.quantity
    WHERE id = NEW.product_id;
END//

CREATE TRIGGER after_stock_exit_item
AFTER INSERT ON stock_exit_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET 
        sortie = sortie + NEW.quantity,
        current_stock = current_stock - NEW.quantity
    WHERE id = NEW.product_id;
END//

DELIMITER ; 