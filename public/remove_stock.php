<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

session_start();
$db = Connection::getInstance();

$error = $success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Validate input
        $productId = $_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $requesterId = $_POST['requester_id'];
        $exitDate = $_POST['exit_date'];
        $bonNumber = $_POST['bon_number'] ?? 'BON-' . date('YmdHis');

        if ($quantity <= 0) {
            throw new Exception("Quantité invalide");
        }

        // Check current stock
        $stmt = $db->prepare("SELECT current_stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $currentStock = $stmt->fetchColumn();

        if ($currentStock < $quantity) {
            throw new Exception("Stock insuffisant");
        }

        // Update product stock
        $stmt = $db->prepare("UPDATE products SET sortie = sortie + ?, current_stock = current_stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $quantity, $productId]);

        // Log the removal
        $stmt = $db->prepare("INSERT INTO stock_exits (demander_id, exit_date, bon_number) VALUES (?, ?, ?)");
        $stmt->execute([$requesterId, $exitDate, $bonNumber]);
        $exitId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO stock_exit_items (exit_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$exitId, $productId, $quantity]);

        $db->commit();
        $_SESSION['success'] = "Stock mis à jour avec succès ($quantity unités retirées)";
        header('Location: remove_stock.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
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
                <div class="mb-3">
                    <label class="form-label">Numéro de Bon</label>
                    <input type="text" name="bon_number" class="form-control" 
                           value="BON-<?= date('YmdHis') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Produit</label>
                    <select name="product_id" id="product_id" class="form-select" required>
                        <option value="">Rechercher un article...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['designation']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Demandeur</label>
                    <select name="requester_id" id="requester_id" class="form-select" required>
                        <option value="">Rechercher un demandeur...</option>
                        <?php foreach ($requesters as $requester): ?>
                            <option value="<?= $requester['id'] ?>"><?= htmlspecialchars($requester['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="exit_date" class="form-label">Date de sortie</label>
                    <input type="date" class="form-control" id="exit_date" name="exit_date" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Quantité</label>
                    <input type="number" name="quantity" class="form-control" required min="1">
                </div>

                <button type="submit" class="btn btn-danger">Sortie de Stock</button>
            </form>
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