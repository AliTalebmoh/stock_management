<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Get top 10 most consumed products
$topProducts = $db->query("
    SELECT 
        p.designation,
        SUM(sei.quantity) as total_quantity
    FROM stock_exit_items sei
    JOIN products p ON sei.product_id = p.id
    GROUP BY p.id, p.designation
    ORDER BY total_quantity DESC
    LIMIT 10
")->fetchAll();

// Get top 10 most active demanders
$topDemanders = $db->query("
    SELECT 
        d.name,
        COUNT(DISTINCT se.id) as total_requests,
        SUM(sei.quantity) as total_items
    FROM stock_exits se
    JOIN demanders d ON se.demander_id = d.id
    JOIN stock_exit_items sei ON se.id = sei.exit_id
    GROUP BY d.id, d.name
    ORDER BY total_items DESC
    LIMIT 10
")->fetchAll();

// Get monthly consumption trend for the current year
$monthlyTrend = $db->query("
    SELECT 
        DATE_FORMAT(se.exit_date, '%Y-%m') as month,
        SUM(sei.quantity) as total_quantity
    FROM stock_exits se
    JOIN stock_exit_items sei ON se.id = sei.exit_id
    WHERE YEAR(se.exit_date) = YEAR(CURRENT_DATE)
    GROUP BY DATE_FORMAT(se.exit_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Prepare data for charts
$productLabels = array_column($topProducts, 'designation');
$productData = array_column($topProducts, 'total_quantity');

$demanderLabels = array_column($topDemanders, 'name');
$demanderData = array_column($topDemanders, 'total_items');

$trendLabels = array_column($monthlyTrend, 'month');
$trendData = array_column($monthlyTrend, 'total_quantity');

// Get overall statistics
$overallStats = $db->query("
    SELECT
        COUNT(DISTINCT p.id) as total_products,
        SUM(p.current_stock) as total_stock,
        SUM(p.entre) as total_entries,
        SUM(p.sortie) as total_exits
    FROM products p
")->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytiques - Gestion des Stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link" href="view_stock.php"><i class="fas fa-boxes"></i> État Stock</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="analytics.php"><i class="fas fa-chart-line"></i> Analytiques</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">Tableau de Bord</h2>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Articles</h5>
                        <p class="card-text h3"><?= number_format($overallStats['total_products']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Stock Actuel</h5>
                        <p class="card-text h3"><?= number_format($overallStats['total_stock']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Entrées</h5>
                        <p class="card-text h3"><?= number_format($overallStats['total_entries']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Sorties</h5>
                        <p class="card-text h3"><?= number_format($overallStats['total_exits']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <!-- Most Consumed Products -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Articles les Plus Consommés</h5>
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Most Active Demanders -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Demandeurs les Plus Actifs</h5>
                        <canvas id="demandersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Consumption Trend -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tendance de Consommation Mensuelle (<?= date('Y') ?>)</h5>
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Most Consumed Products Chart
        new Chart(document.getElementById('productsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($productLabels) ?>,
                datasets: [{
                    label: 'Quantité Consommée',
                    data: <?= json_encode($productData) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Most Active Demanders Chart
        new Chart(document.getElementById('demandersChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($demanderLabels) ?>,
                datasets: [{
                    label: 'Articles Demandés',
                    data: <?= json_encode($demanderData) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Monthly Consumption Trend Chart
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Consommation Mensuelle',
                    data: <?= json_encode($trendData) ?>,
                    fill: false,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 