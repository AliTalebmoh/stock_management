<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Fetch all products
$products = $db->query("SELECT * FROM products ORDER BY designation")->fetchAll();
// Fetch all suppliers
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("INSERT INTO stock_entries (product_id, supplier_id, quantity, entry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['product_id'],
            $_POST['supplier_id'],
            $_POST['quantity'],
            $_POST['entry_date']
        ]);
        $success = "Entrée de stock ajoutée avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout de l'entrée de stock : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrée de Stock - Gestion des Stocks</title>
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
        }
        .btn-success {
            background-color: #006837;
            border-color: #006837;
        }
        .btn-success:hover {
            background-color: #005a2f;
            border-color: #005a2f;
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
        }
        .input-group {
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            min-height: 42px;
            position: relative;
            flex-wrap: nowrap;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-right: none;
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            min-height: 42px;
            position: relative;
            z-index: 3;
            width: 42px;
            justify-content: center;
        }
        
        .form-select, .form-control {
            border: 1px solid #dee2e6;
            border-left: none;
            padding: 0.6rem 1rem;
            min-height: 42px;
        }

        /* Update Select2 specific styles */
        .select2-container--bootstrap-5 {
            flex: 1 1 auto !important;
            width: 1% !important;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dee2e6;
            border-left: none;
            min-height: 42px !important;
            height: 42px !important;
            display: flex;
            align-items: center;
            padding: 0 0.5rem;
        }

        /* Ensure the select element itself doesn't affect layout */
        .form-select {
            flex: 1 1 auto;
            width: 1%;
        }

        /* Fix input group border radius */
        .input-group > :first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }
        
        .input-group > :last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }

        /* Ensure consistent height for number input */
        input[type="number"].form-control {
            height: 42px;
            line-height: 1.5;
        }

        /* Select2 specific styles */
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dee2e6;
            border-left: none;
            min-height: 42px !important;
            padding: 0.6rem 1rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            padding-top: 0;
            padding-bottom: 0;
            display: flex;
            align-items: center;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: normal;
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #dee2e6;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }

        .select2-container--bootstrap-5 .select2-search__field {
            padding: 0.5rem;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/logo.png" alt="AL AKHAWAYN UNIVERSITY">
                <span class="system-title">Gestion des Stocks</span>
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
                        <a class="nav-link active" href="add_stock.php"><i class="fas fa-plus-circle"></i> Entrée Stock</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="remove_stock.php"><i class="fas fa-minus-circle"></i> Sortie Stock</a>
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
            <h2 class="mb-0">Entrée de Stock</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Entrée Stock</li>
                </ol>
            </nav>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label">Article</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">Sélectionner un article</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label">Fournisseur</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-truck"></i></span>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <option value="">Sélectionner un fournisseur</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label">Quantité</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   required min="1" placeholder="Entrer la quantité">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="entry_date" class="form-label">Date d'entrée</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input type="date" class="form-control" id="entry_date" name="entry_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-times me-1"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> Ajouter au Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

            // Initialize Select2 for supplier selection
            $('#supplier_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Sélectionner un fournisseur',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Aucun fournisseur trouvé";
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