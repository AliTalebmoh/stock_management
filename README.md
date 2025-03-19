# Stock Management System

A comprehensive stock management system built with PHP and MySQL, featuring stock tracking, "Bon de sortie" generation, and analytics.

## Features

- Add products to stock with supplier information
- Generate "Bon de sortie" for stock exits
- Track current inventory levels
- View analytics and reports
- Export data to Excel format

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/stock_management.git
cd stock_management
```

2. Install dependencies:
```bash
composer install
```

3. Create the database:
```bash
mysql -u root -p < database/schema.sql
php -S localhost:8000 -t public/
php import_articles.php "Liste d'articles.csv"
```

4. Configure your database connection:
   - Copy `config/database.php.example` to `config/database.php`
   - Update the database credentials in `config/database.php`

5. Set up your web server:
   - Configure the document root to point to the `public` directory
   - Ensure the web server has write permissions for generating Excel files

6. Initialize the database with required data:
   - Add initial products
   - Add suppliers
   - Add demanders

## Usage

1. Access the application through your web browser
2. Use the dashboard to navigate between different functions:
   - Add to Stock
   - Remove from Stock (Generate Bon de sortie)
   - View Current Stock
   - Analytics Dashboard

## Security

- All user inputs are sanitized
- SQL injection prevention through prepared statements
- XSS protection through proper HTML escaping

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.