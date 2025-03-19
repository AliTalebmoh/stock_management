-- Insert sample suppliers
INSERT INTO suppliers (name, contact, phone, email) VALUES
('Office Depot', 'John Smith', '+212 666-123456', 'john.smith@officedepot.com'),
('Tech Solutions', 'Sarah Johnson', '+212 677-789012', 'sarah@techsolutions.com'),
('Paper World', 'Mohammed Ali', '+212 688-345678', 'mali@paperworld.com'),
('Computer Supplies', 'Lisa Brown', '+212 699-901234', 'lisa@computersupplies.com');

-- Insert sample demanders (departments)
INSERT INTO demanders (name, department, contact) VALUES
('IT Department', 'Information Technology', 'David Wilson'),
('HR Office', 'Human Resources', 'Emma Davis'),
('Finance Dept', 'Finance', 'Omar Hassan'),
('Academic Affairs', 'Education', 'Prof. Maria Garcia'),
('Student Services', 'Administration', 'Ahmed Khalil'),
('Library', 'Academic', 'Dr. James White');

-- Insert sample products
INSERT INTO products (designation, description, report_stock, entre, sortie, current_stock) VALUES
('HP Printer Paper A4', 'High-quality white printer paper, 80gsm', 500, 1000, 800, 700),
('Stapler', 'Heavy duty stapler', 50, 100, 30, 120),
('Pencils HB', 'Standard HB pencils box of 12', 200, 500, 400, 300),
('Whiteboard Markers', 'Assorted colors pack of 4', 100, 300, 250, 150),
('Sticky Notes', 'Yellow 3x3 inches pack of 12', 150, 400, 300, 250),
('USB Flash Drive 32GB', 'High-speed USB 3.0 flash drive', 30, 100, 80, 50),
('Printer Toner HP-85A', 'Black toner cartridge for HP printers', 20, 50, 40, 30),
('File Folders', 'Manila file folders pack of 50', 300, 600, 500, 400),
('Notebooks', 'Spiral bound A4 notebooks', 250, 500, 400, 350),
('Paper Clips', 'Small metal paper clips box of 100', 100, 300, 200, 200);

-- Insert sample stock entries
INSERT INTO stock_entries (product_id, supplier_id, quantity, entry_date) VALUES
(1, 1, 500, '2024-01-15'),
(1, 3, 500, '2024-02-20'),
(2, 1, 100, '2024-01-10'),
(3, 3, 500, '2024-02-01'),
(4, 2, 300, '2024-01-25'),
(5, 1, 400, '2024-02-10'),
(6, 2, 100, '2024-03-01'),
(7, 4, 50, '2024-02-15'),
(8, 1, 600, '2024-01-20'),
(9, 3, 500, '2024-03-05'),
(10, 1, 300, '2024-02-28');

-- Insert sample stock exits
INSERT INTO stock_exits (bon_number, exit_date, demander_id) VALUES
('0001/2024', '2024-01-20', 1),
('0002/2024', '2024-02-05', 2),
('0003/2024', '2024-02-15', 3),
('0004/2024', '2024-03-01', 4),
('0005/2024', '2024-03-10', 5);

-- Insert sample stock exit items
INSERT INTO stock_exit_items (exit_id, product_id, quantity, utilisation) VALUES
(1, 1, 200, 'Monthly office supply'),
(1, 3, 100, 'Staff requirements'),
(1, 5, 50, 'Office use'),
(2, 2, 10, 'Department needs'),
(2, 4, 50, 'Teaching supplies'),
(3, 6, 20, 'Data storage'),
(3, 7, 10, 'Printer maintenance'),
(4, 8, 100, 'File organization'),
(4, 9, 100, 'Student supplies'),
(5, 10, 50, 'Office organization'),
(5, 1, 100, 'Printing needs'); 