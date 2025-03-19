<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = Connection::getInstance();

// Fetch all products with current stock
$products = $db->query("
    SELECT 
        id, 
        designation, 
        current_stock 
    FROM products 
    WHERE current_stock > 0 
    ORDER BY designation
")->fetchAll();

// Fetch all demanders
$demanders = $db->query("SELECT * FROM demanders ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Get the next bon number
        $year = date('Y');
        $stmt = $db->query("
            SELECT MAX(CAST(SUBSTRING_INDEX(bon_number, '/', 1) AS UNSIGNED)) as max_num 
            FROM stock_exits 
            WHERE bon_number LIKE '%/$year'
        ");
        $result = $stmt->fetch();
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $bonNumber = sprintf("%04d/%d", $nextNum, $year);

        // Insert stock exit
        $stmt = $db->prepare("
            INSERT INTO stock_exits (bon_number, exit_date, demander_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $bonNumber,
            $_POST['exit_date'],
            $_POST['demander_id']
        ]);
        $exitId = $db->lastInsertId();

        // Validate stock availability before processing
        $errors = [];
        foreach ($_POST['products'] as $index => $productId) {
            if (!empty($productId) && !empty($_POST['quantities'][$index])) {
                $stmt = $db->prepare("SELECT current_stock FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if ($product['current_stock'] < $_POST['quantities'][$index]) {
                    $errors[] = "Insufficient stock for product ID: $productId";
                }
            }
        }

        if (empty($errors)) {
            // Insert stock exit items
            $stmt = $db->prepare("
                INSERT INTO stock_exit_items (exit_id, product_id, quantity, utilisation) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($_POST['products'] as $index => $productId) {
                if (!empty($productId) && !empty($_POST['quantities'][$index])) {
                    $stmt->execute([
                        $exitId,
                        $productId,
                        $_POST['quantities'][$index],
                        $_POST['utilisations'][$index] ?? ''
                    ]);
                }
            }

            // Generate Excel document
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set landscape orientation and paper size
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
            
            // Set margins (in inches)
            $sheet->getPageMargins()->setTop(0.5);
            $sheet->getPageMargins()->setRight(0.5);
            $sheet->getPageMargins()->setLeft(0.5);
            $sheet->getPageMargins()->setBottom(0.5);

            // Center the content horizontally on the page
            $sheet->getPageSetup()->setHorizontalCentered(true);
            
            // Add logo with larger size
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo');
            $drawing->setPath(__DIR__ . '/assets/images/logo.png');
            $drawing->setHeight(85); // Adjusted for landscape
            $drawing->setCoordinates('B1');
            $drawing->setOffsetX(5);
            $drawing->setWorksheet($sheet);

            // Adjust row heights for logo
            $sheet->getRowDimension(1)->setRowHeight(42);
            $sheet->getRowDimension(2)->setRowHeight(42);
            
            // Set date next to logo
            $sheet->setCellValue('H2', 'Date      /      / 2025');
            $sheet->getStyle('H2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H2')->getFont()->setSize(11)->setBold(true);
            
            // Add spacing after logo
            $sheet->getRowDimension(3)->setRowHeight(15);
            
            // Set bon number with correct year
            $sheet->setCellValue('B4', 'Bon de sortie N°............    /2025');
            $sheet->getStyle('B4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B4')->getFont()->setSize(12)->setBold(true);
            
            // Add spacing before table
            $sheet->getRowDimension(5)->setRowHeight(10);
            
            // Set table rows with proper height and alignment
            $sheet->setCellValue('B6', 'Article');
            $sheet->setCellValue('B7', 'Référence');
            $sheet->setCellValue('B8', 'Spécification');
            $sheet->setCellValue('B9', 'Quantité');
            $sheet->setCellValue('B10', 'Utilisation');

            // Style the headers and rows
            $sheet->getStyle('A4:G4')->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A6:A10')->getFont()->setBold(true)->setSize(10);
            
            // Set row heights for table
            foreach (range(6, 10) as $row) {
                $sheet->getRowDimension($row)->setRowHeight(25);
            }
            
            // Center align the table headers
            $sheet->getStyle('B6:B10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B6:B10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            // Fetch exit items with product details
            $stmt = $db->prepare("
                SELECT 
                    p.designation, 
                    p.id as reference, 
                    sei.quantity, 
                    sei.utilisation
                FROM stock_exit_items sei
                JOIN products p ON sei.product_id = p.id
                WHERE sei.exit_id = ?
            ");
            $stmt->execute([$exitId]);
            $items = $stmt->fetchAll();

            // Calculate how many columns we need (minimum 6, or more if more items)
            $numItems = count($items);
            $numColumns = max(6, $numItems);
            $lastCol = chr(66 + $numColumns - 1); // B + number of columns - 1

            // Fill data in columns with proper alignment
            $col = 'C';
            foreach ($items as $item) {
                $sheet->setCellValue($col . '6', $item['designation']);
                $sheet->setCellValue($col . '7', $item['reference']);
                $sheet->setCellValue($col . '8', ''); // Specification always empty
                $sheet->setCellValue($col . '9', $item['quantity']);
                $sheet->setCellValue($col . '10', $item['utilisation']);
                
                // Center align all cells in this column
                $sheet->getStyle($col . '6:' . $col . '10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle($col . '6:' . $col . '10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                // Add padding to cells
                $sheet->getStyle($col . '6:' . $col . '10')->getAlignment()->setIndent(1);
                
                $col++;
            }

            // Fill remaining columns with empty cells if less than 6 items
            while ($col <= $lastCol) {
                $sheet->setCellValue($col . '6', '');
                $sheet->setCellValue($col . '7', '');
                $sheet->setCellValue($col . '8', '');
                $sheet->setCellValue($col . '9', '');
                $sheet->setCellValue($col . '10', '');
                
                // Center align empty cells
                $sheet->getStyle($col . '6:' . $col . '10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle($col . '6:' . $col . '10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                $col++;
            }

            // Set borders for the entire table
            $tableBorders = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];
            
            // Apply borders to the table (always at least 6 columns or more if needed)
            $sheet->getStyle('A6:' . $lastCol . '10')->applyFromArray($tableBorders);
            
            // Set column widths optimized for landscape
            $sheet->getColumnDimension('A')->setWidth(3); // Margin
            $sheet->getColumnDimension('B')->setWidth(20); // Labels
            // Set width for all data columns
            for ($col = 'C'; $col <= $lastCol; $col++) {
                $sheet->getColumnDimension($col)->setWidth(25);
            }
            
            // Set width for the last columns
            $sheet->getColumnDimension('H')->setWidth(25); // Date column
            $sheet->getColumnDimension('I')->setWidth(3); // Right margin



            // Fetch demander details
            $stmt = $db->prepare("SELECT name, department FROM demanders WHERE id = ?");
            $stmt->execute([$_POST['demander_id']]);
            $demander = $stmt->fetch();

            // Add spacing before footer
            $footerRow = 12; // Start footer at fixed position
            $sheet->getRowDimension($footerRow-1)->setRowHeight(15); // Add spacing
            
            // Add footer information with proper alignment
            $sheet->setCellValue('B' . $footerRow, 'Demandeur :');
            $sheet->setCellValue($lastCol . $footerRow, 'Responsable du stock      N,HERRAR');
            $sheet->getStyle('B' . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle($lastCol . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            
            // Add spacing between footer lines
            $footerRow += 4;
            $sheet->getRowDimension($footerRow-1)->setRowHeight(25);
            
            // Add second footer line
            $sheet->setCellValue('B' . $footerRow, 'Manager:');
            $sheet->setCellValue($lastCol . $footerRow, 'Responsable des achats:');
            $sheet->getStyle('B' . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle($lastCol . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            
            // Set row height for footer lines
            $sheet->getRowDimension($footerRow)->setRowHeight(25);
            $sheet->getRowDimension($footerRow-4)->setRowHeight(25);

            // Send Excel file directly to browser
            $db->commit();
            $filename = 'bon_de_sortie_' . $bonNumber . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } else {
            throw new Exception(implode("<br>", $errors));
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error recording stock exit: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sortie de Stock - Gestion des Stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-brand img {
            height: 60px;
            margin-right: 15px;
        }
        .navbar {
            padding: 0.5rem 1rem;
            background: #fff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .system-title {
            color: #006837;
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0;
        }
        .nav-link {
            color: #333 !important;
            font-weight: 500;
        }
        .nav-link:hover {
            color: #006837 !important;
        }
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
        }
        .product-entry {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }
        .product-entry:last-child {
            margin-bottom: 0;
        }
        .product-entry .form-label {
            color: #495057;
        }
        .remove-product {
            transition: all 0.2s;
        }
        .remove-product:hover {
            transform: translateY(-1px);
        }
        #add-product {
            transition: transform 0.2s;
        }
        #add-product:hover {
            transform: translateY(-1px);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #ced4da;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/logo.png" alt="AL AKHAWAYN UNIVERSITY">
                <span class="system-title">Stock Management System</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_stock.php"><i class="fas fa-plus-circle"></i> Entrée Stock</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="remove_stock.php"><i class="fas fa-minus-circle"></i> Sortie Stock</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_stock.php"><i class="fas fa-boxes"></i> État Stock</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-line"></i> Analytiques</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Remove from Stock</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Remove Stock</li>
                </ol>
            </nav>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="" id="removeStockForm">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="demander_id" class="form-label">Demandeur</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <select class="form-select" id="demander_id" name="demander_id" required>
                                <option value="">Sélectionner un demandeur</option>
                                <?php foreach ($demanders as $demander): ?>
                                    <option value="<?= $demander['id'] ?>">
                                        <?= htmlspecialchars($demander['name']) ?> (<?= htmlspecialchars($demander['department']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="exit_date" class="form-label">Date de Sortie</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input type="date" class="form-control" id="exit_date" name="exit_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div id="products-container">
                    <div class="product-entry">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Détails du Produit</h5>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-product d-none">
                                <i class="fas fa-trash me-1"></i> Supprimer
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Article</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                                    <select class="form-select product-select" name="products[]" required>
                                        <option value="">Sélectionner un article</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?= $product['id'] ?>" data-stock="<?= $product['current_stock'] ?>">
                                                <?= htmlspecialchars($product['designation']) ?> 
                                                (Stock: <?= $product['current_stock'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantité</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                                    <input type="number" class="form-control quantity-input" name="quantities[]" 
                                           required min="1" max="999999" placeholder="Entrer la quantité">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Utilisation</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                    <input type="text" class="form-control" name="utilisations[]" 
                                           required placeholder="Préciser l'utilisation">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-light mt-3" id="add-product">
                    <i class="fas fa-plus-circle"></i> Ajouter un Autre Produit
                </button>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-times me-1"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-minus-circle me-1"></i> Générer Bon de Sortie
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const productsContainer = $('#products-container');
            const addProductBtn = $('#add-product');

            // Function to create a new product entry
            function createProductEntry() {
                const template = $('.product-entry:first').clone();
                template.find('select').val('');
                template.find('input').val('');
                template.find('.remove-product').removeClass('d-none');
                template.find('.invalid-feedback').remove();
                template.find('.is-invalid').removeClass('is-invalid');
                return template;
            }

            // Function to update remove button visibility
            function updateRemoveButtons() {
                const entries = $('.product-entry');
                entries.each(function(index) {
                    const removeBtn = $(this).find('.remove-product');
                    if (entries.length === 1) {
                        removeBtn.addClass('d-none');
                    } else {
                        removeBtn.removeClass('d-none');
                    }
                });
            }

            // Add product button click handler with animation
            addProductBtn.click(function() {
                const newEntry = createProductEntry();
                newEntry.hide();
                productsContainer.append(newEntry);
                newEntry.slideDown(300);
                updateRemoveButtons();
                // Smooth scroll to new entry
                $('html, body').animate({
                    scrollTop: newEntry.offset().top - 100
                }, 500);
            });

            // Remove product button click handler with animation
            productsContainer.on('click', '.remove-product', function() {
                const entry = $(this).closest('.product-entry');
                entry.slideUp(300, function() {
                    entry.remove();
                    updateRemoveButtons();
                });
            });

            // Stock validation with enhanced feedback
            productsContainer.on('change', '.product-select', function() {
                const selectedOption = $(this).find(':selected');
                const maxStock = selectedOption.data('stock');
                const quantityInput = $(this).closest('.product-entry').find('.quantity-input');
                
                quantityInput.attr('max', maxStock);
                quantityInput.attr('placeholder', `Entrer la quantité (max: ${maxStock})`);
                // Reset validation state
                quantityInput.removeClass('is-invalid');
                quantityInput.next('.invalid-feedback').remove();
            });

            // Real-time quantity validation
            productsContainer.on('input', '.quantity-input', function() {
                const value = parseInt($(this).val());
                const max = parseInt($(this).attr('max'));
                
                if (value > max) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after(`<div class="invalid-feedback">La quantité ne peut pas dépasser ${max}</div>`);
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            });

            // Enhanced form validation
            $('#removeStockForm').on('submit', function(e) {
                let isValid = true;
                const selectedProducts = new Set();
                let firstError = null;

                $('.product-select').each(function() {
                    const productId = $(this).val();
                    const entry = $(this).closest('.product-entry');
                    
                    if (productId) {
                        if (selectedProducts.has(productId)) {
                            $(this).addClass('is-invalid');
                            if (!$(this).next('.invalid-feedback').length) {
                                $(this).after('<div class="invalid-feedback">Ce produit a déjà été sélectionné</div>');
                            }
                            isValid = false;
                            if (!firstError) firstError = $(this);
                        } else {
                            selectedProducts.add(productId);
                            $(this).removeClass('is-invalid');
                            $(this).next('.invalid-feedback').remove();

                            const stock = $(this).find(':selected').data('stock');
                            const quantityInput = entry.find('.quantity-input');
                            const quantity = parseInt(quantityInput.val());
                            
                            if (quantity > stock) {
                                quantityInput.addClass('is-invalid');
                                if (!quantityInput.next('.invalid-feedback').length) {
                                    quantityInput.after(`<div class="invalid-feedback">La quantité ne peut pas dépasser ${stock}</div>`);
                                }
                                isValid = false;
                                if (!firstError) firstError = quantityInput;
                            }
                        }
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    if (firstError) {
                        $('html, body').animate({
                            scrollTop: firstError.offset().top - 100
                        }, 500);
                        firstError.focus();
                    }
                }
            });
        });
    </script>
</body>
</html>