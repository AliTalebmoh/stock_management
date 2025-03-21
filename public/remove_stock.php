<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

// Custom PDF class
class BonDeSortiePDF extends \TCPDF {
    // Page header
    public function Header() {
        // Logo - Add error suppression operator (@) to suppress libpng warnings - smaller logo
        @$this->Image(__DIR__ . '/assets/images/logo.png', 15, 10, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Add BON DE SORTIE title beside the logo
        $this->SetFont('helvetica', 'B', 20);
        $this->SetXY(80, 20);
        $this->Cell(100, 10, 'BON DE SORTIE', 0, 0, 'L');
    }

    // Page footer - empty
    public function Footer() {
        // Empty footer
    }
}

session_start();
$db = Connection::getInstance();

$error = $success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Check if this is the final submission or adding an item
        if (isset($_POST['add_item'])) {
            // Validate the item
            $productId = $_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            
            if ($quantity <= 0) {
                throw new Exception("Quantité invalide");
            }
            
            // Check current stock
            $stmt = $db->prepare("SELECT current_stock, designation FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Produit non trouvé");
            }
            
            if ($product['current_stock'] < $quantity) {
                throw new Exception("Stock insuffisant pour {$product['designation']}");
            }
            
            // Add to session cart
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $_SESSION['cart'][] = [
                'product_id' => $productId,
                'designation' => $product['designation'],
                'quantity' => $quantity
            ];
            
            $_SESSION['requester_id'] = $_POST['requester_id'];
            $_SESSION['exit_date'] = $_POST['exit_date'];
            $_SESSION['bon_number'] = $_POST['bon_number'];
            
            $success = "Article ajouté au bon de sortie";
        } 
        elseif (isset($_POST['remove_item'])) {
            // Remove item from cart
            $index = (int)$_POST['item_index'];
            if (isset($_SESSION['cart'][$index])) {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
                $success = "Article retiré du bon de sortie";
            }
        }
        elseif (isset($_POST['generate_bon'])) {
            // Final submission - create the bon de sortie
            if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
                throw new Exception("Aucun article dans le bon de sortie");
            }
            
            $requesterId = $_SESSION['requester_id'];
            $exitDate = $_SESSION['exit_date'];
            $bonNumber = $_SESSION['bon_number'];
            
            // Create the exit record
            $stmt = $db->prepare("INSERT INTO stock_exits (demander_id, exit_date, bon_number) VALUES (?, ?, ?)");
            $stmt->execute([$requesterId, $exitDate, $bonNumber]);
            $exitId = $db->lastInsertId();
            
            // Add all items
            foreach ($_SESSION['cart'] as $item) {
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET sortie = sortie + ?, current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
                
                // Add exit item
                $stmt = $db->prepare("INSERT INTO stock_exit_items (exit_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$exitId, $item['product_id'], $item['quantity']]);
            }
            
            $db->commit();
            
            // Generate and download PDF
            generatePDF($exitId, $db);
            
            // Clear the cart
            unset($_SESSION['cart']);
            unset($_SESSION['requester_id']);
            unset($_SESSION['exit_date']);
            unset($_SESSION['bon_number']);
            
            $_SESSION['success'] = "Bon de sortie généré avec succès";
            header('Location: remove_stock.php');
            exit;
        }
        elseif (isset($_POST['clear_cart'])) {
            // Clear the cart
            unset($_SESSION['cart']);
            unset($_SESSION['requester_id']);
            unset($_SESSION['exit_date']);
            unset($_SESSION['bon_number']);
            $success = "Bon de sortie annulé";
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

/**
 * Generate PDF for Bon de Sortie
 */
function generatePDF($exitId, $db) {
    // Get exit data
    $stmt = $db->prepare("
        SELECT 
            e.id, e.bon_number, e.exit_date, 
            d.name as demander_name, d.department
        FROM 
            stock_exits e
            JOIN demanders d ON e.demander_id = d.id
        WHERE 
            e.id = ?
    ");
    $stmt->execute([$exitId]);
    $exitData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get exit items
    $stmt = $db->prepare("
        SELECT 
            i.quantity, 
            p.id as article_id, p.designation
        FROM 
            stock_exit_items i
            JOIN products p ON i.product_id = p.id
        WHERE 
            i.exit_id = ?
    ");
    $stmt->execute([$exitId]);
    $exitItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create new PDF document
    $pdf = new BonDeSortiePDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Stock Management System');
    $pdf->SetAuthor('Al Akhawayn University');
    $pdf->SetTitle('Bon de Sortie - ' . $exitData['bon_number']);
    $pdf->SetSubject('Bon de Sortie');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Add date and bon information
    $pdf->SetY(50);
    $dateObj = new DateTime($exitData['exit_date']);
    $day = $dateObj->format('d');
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');
    
    // Info box header - only date and bon number, removed demandeur
    $pageWidth = $pdf->getPageWidth() - 30; // 30mm margins total
    $halfWidth = $pageWidth / 2;
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($halfWidth, 10, 'DATE: ' . $day . '/' . $month . '/' . $year, 1, 0, 'L');
    $pdf->Cell($halfWidth, 10, 'BON N°: ' . $exitData['bon_number'], 1, 1, 'L');
    
    // Main table header
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Calculate cell widths based on available page width 
    $pageWidth = $pdf->getPageWidth() - 30; // 30mm margins total
    $articleWidth = $pageWidth * 0.25;  // 25%
    $refWidth = $pageWidth * 0.2;       // 20%
    $specWidth = $pageWidth * 0.4;      // 40%
    $qteWidth = $pageWidth * 0.15;      // 15%
    
    // Ensure we have at least one item
    if (empty($exitItems)) {
        $exitItems = [
            [
                'article_id' => '',
                'designation' => '',
                'quantity' => ''
            ]
        ];
    }
    
    // Create table header row
    $pdf->Cell($articleWidth, 10, 'ARTICLE', 1, 0, 'C');
    $pdf->Cell($refWidth, 10, 'RÉFÉRENCE', 1, 0, 'C');
    $pdf->Cell($specWidth, 10, 'SPÉCIFICATION', 1, 0, 'C');
    $pdf->Cell($qteWidth, 10, 'QTÉ', 1, 1, 'C');
    
    // Add table rows
    $pdf->SetFont('helvetica', '', 10);
    
    foreach ($exitItems as $item) {
        // Get first word of designation for Article name
        $parts = explode(' ', $item['designation']);
        $article = $parts[0];
        
        $pdf->Cell($articleWidth, 10, $article, 1, 0, 'L');
        $pdf->Cell($refWidth, 10, $item['article_id'], 1, 0, 'C');
        $pdf->Cell($specWidth, 10, '', 1, 0, 'L'); // Empty specification as requested
        $pdf->Cell($qteWidth, 10, $item['quantity'], 1, 1, 'C');
    }
    
    // Add the demandeur and responsable info line in the table
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($pageWidth/2, 10, 'Demandeur : ' . $exitData['demander_name'], 1, 0, 'L');
    $pdf->Cell($pageWidth/2, 10, 'Responsable du stock : N,HERRAR', 1, 1, 'R');
    
    // Add a small space after the table before signature section
    $pdf->Ln(20);
    
    // Signature section - split into two sections as shown in the image
    $halfWidth = $pageWidth / 2;
    
    // Manager on left side
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($halfWidth/2, 10, 'Manager:', 0, 0, 'L');
    $pdf->Cell($halfWidth/2, 10, '', 0, 0, 'L');
    
    // Responsable des achats on right side (right aligned)
    $pdf->Cell($halfWidth/2, 10, 'Responsable des achats:', 0, 0, 'R');
    $pdf->Cell($halfWidth/2, 10, '', 0, 1, 'L');
    
    // Get uploads directory path and ensure it exists
    $exportsDir = __DIR__ . '/exports';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }
    
    // Save PDF file
    $pdfFilename = 'exports/bon_sortie_' . $exitData['bon_number'] . '.pdf';
    $pdf->Output(__DIR__ . '/' . $pdfFilename, 'F');
    
    // JavaScript to trigger file save dialog
    echo '<script>
        // Show save dialog
        let saveLink = document.createElement("a");
        saveLink.href = "' . $pdfFilename . '";
        saveLink.download = "Bon_de_sortie_' . $exitData['bon_number'] . '.pdf";
        saveLink.click();
        
        // Redirect after a short delay
        setTimeout(function() {
            window.location.href = "remove_stock.php";
        }, 1500);
    </script>';
    exit;
}

// Get products and requesters
$products = $db->query("SELECT id, designation FROM products ORDER BY designation")->fetchAll();
$requesters = $db->query("SELECT id, name FROM demanders ORDER BY name")->fetchAll();

// Display success message if set
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sortie de Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        .cart-table {
            margin-top: 20px;
        }
        .cart-empty {
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 20px;
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
            <h2 class="mb-0">Sortie de Stock</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sortie Stock</li>
                </ol>
            </nav>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Succès!</strong> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Numéro de Bon</label>
                        <input type="text" name="bon_number" class="form-control" 
                               value="<?= $_SESSION['bon_number'] ?? 'BON-' . date('YmdHis') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Demandeur</label>
                        <select name="requester_id" id="requester_id" class="form-select" required>
                            <option value="">Sélectionner un demandeur</option>
                            <?php foreach ($requesters as $requester): ?>
                                <option value="<?= $requester['id'] ?>" <?= (isset($_SESSION['requester_id']) && $_SESSION['requester_id'] == $requester['id']) ? 'selected' : '' ?>><?= htmlspecialchars($requester['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="exit_date" class="form-label">Date de sortie</label>
                        <input type="date" class="form-control" id="exit_date" name="exit_date" 
                               value="<?= $_SESSION['exit_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Produit</label>
                        <select name="product_id" id="product_id" class="form-select">
                            <option value="">Rechercher un article...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantité</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-2 d-flex align-items-end mb-3">
                        <button type="submit" name="add_item" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-1"></i> Ajouter
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Cart Table -->
            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <div class="cart-table">
                    <h4 class="mb-3">Articles dans le bon de sortie</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Désignation</th>
                                    <th>Quantité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['designation']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="item_index" value="<?= $index ?>">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-3">
                        <form method="POST" class="me-2">
                            <button type="submit" name="clear_cart" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Annuler
                            </button>
                        </form>
                        <form method="POST">
                            <button type="submit" name="generate_bon" class="btn btn-danger">
                                <i class="fas fa-file-pdf me-1"></i> Générer le Bon de Sortie
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                    <p class="mb-0">Aucun article dans le bon de sortie</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for product selection
            $('#product_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Rechercher un article...',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Aucun article trouvé";
                    },
                    searching: function() {
                        return "Recherche...";
                    }
                }
            });

            // Initialize Select2 for requester selection
            $('#requester_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Sélectionner un demandeur',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Aucun demandeur trouvé";
                    },
                    searching: function() {
                        return "Recherche...";
                    }
                }
            });
        });
    </script>

</body>
</html>