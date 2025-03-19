-- Create requesters table
CREATE TABLE IF NOT EXISTS requesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    contact VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('Enseignant', 'Administratif', 'Étudiant', 'Personnel', 'Autre') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create stock_requests table
CREATE TABLE IF NOT EXISTS stock_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('En attente', 'Approuvé', 'Refusé') DEFAULT 'En attente',
    notes TEXT,
    FOREIGN KEY (requester_id) REFERENCES requesters(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
