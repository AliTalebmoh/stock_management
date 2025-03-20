<?php
require_once __DIR__ . '/../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
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
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/images/logo.png" alt="AL AKHAWAYN UNIVERSITY">
                <span class="system-title">Gestion des Stocks</span>
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row g-4">
            <!-- Transactions History Card -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-history fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Historique</h5>
                        <p class="card-text">Consulter l'historique des entrées et sorties</p>
                        <a href="transactions.php" class="btn btn-primary">Voir Historique</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Entrée de Stock</h5>
                        <p class="card-text">Ajouter de nouveaux articles à l'inventaire</p>
                        <a href="add_stock.php" class="btn btn-success">Ajouter Articles</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-minus-circle fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Sortie de Stock</h5>
                        <p class="card-text">Générer des bons de sortie</p>
                        <a href="remove_stock.php" class="btn btn-danger">Retirer Articles</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                        <h5 class="card-title">État du Stock</h5>
                        <p class="card-text">Consulter les niveaux d'inventaire actuels</p>
                        <a href="view_stock.php" class="btn btn-info text-white">Voir Stock</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-cogs fa-3x text-warning mb-3"></i>
                        <h5 class="card-title">Gestion</h5>
                        <p class="card-text">Gérer les fournisseurs et demandeurs</p>
                        <a href="gestion.php" class="btn btn-warning text-dark">Gérer</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Analytiques</h5>
                        <p class="card-text">Consulter les analyses et rapports</p>
                        <a href="analytics.php" class="btn btn-primary">Voir Analytiques</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 