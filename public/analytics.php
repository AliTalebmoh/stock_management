<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

try {
    $db = Connection::getInstance();

    // Get overall statistics - Simplified and more reliable
    $overallStats = $db->query("
        SELECT
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(p.current_stock), 0) as total_stock,
            COALESCE(SUM(p.entre), 0) as total_entries,
            COALESCE(SUM(p.sortie), 0) as total_exits,
            COALESCE((
                SELECT SUM(sei.quantity)
                FROM stock_exit_items sei
                JOIN stock_exits se ON se.id = sei.exit_id
                WHERE YEAR(se.exit_date) = YEAR(CURRENT_DATE)
            ), 0) as current_year_exits,
            COALESCE((
                SELECT SUM(sei.quantity)
                FROM stock_exit_items sei
                JOIN stock_exits se ON se.id = sei.exit_id
                WHERE YEAR(se.exit_date) = YEAR(CURRENT_DATE) - 1
            ), 0) as previous_year_exits
        FROM products p
    ")->fetch(PDO::FETCH_ASSOC);

    // Get category statistics - Simplified and more accurate
    $categoryStats = $db->query("
        SELECT
            COALESCE(p.category, 'Non catégorisé') as category,
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(p.current_stock), 0) as total_stock,
            COALESCE(SUM(p.entre), 0) as total_entries,
            COALESCE(SUM(p.sortie), 0) as total_exits
        FROM products p
        GROUP BY p.category
        ORDER BY total_products DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get most consumed products - Simplified query
    $topProducts = $db->query("
        SELECT 
            p.designation,
            p.sortie as total_quantity
        FROM products p
        WHERE p.sortie > 0
        ORDER BY p.sortie DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get most active demanders - Based on stock_exits
    $topDemanders = $db->query("
        SELECT 
            d.name,
            d.department,
            COUNT(DISTINCT se.id) as request_count,
            COALESCE(SUM(sei.quantity), 0) as total_items
        FROM demanders d
        JOIN stock_exits se ON d.id = se.demander_id
        JOIN stock_exit_items sei ON se.id = sei.exit_id
        GROUP BY d.id, d.name, d.department
        ORDER BY total_items DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Debug logging
    error_log("Overall Stats: " . print_r($overallStats, true));
    error_log("Category Stats: " . print_r($categoryStats, true));
    error_log("Top Products: " . print_r($topProducts, true));
    error_log("Top Demanders: " . print_r($topDemanders, true));

    // Prepare data for charts
    $productNames = array_column($topProducts, 'designation');
    $productQuantities = array_column($topProducts, 'total_quantity');
    
    $demanderNames = array_map(function($d) {
        return $d['name'] . ' (' . $d['department'] . ')';
    }, $topDemanders);
    $demanderQuantities = array_column($topDemanders, 'total_items');

} catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
    $productNames = [];
    $productQuantities = [];
    $demanderNames = [];
    $demanderQuantities = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Stock Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            min-height: 400px;
            margin-bottom: 2rem;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Tableau de Bord</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item active">Analytiques</li>
                </ol>
            </nav>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary">Total Products</h5>
                        <p class="card-text display-6"><?= number_format($overallStats['total_products']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success">Current Stock</h5>
                        <p class="card-text display-6"><?= number_format($overallStats['total_stock']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info">Total Entries</h5>
                        <p class="card-text display-6"><?= number_format($overallStats['total_entries']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning">Total Exits</h5>
                        <p class="card-text display-6"><?= number_format($overallStats['total_exits']) ?></p>
                        <small class="text-<?= $yoyChange >= 0 ? 'success' : 'danger' ?>">
                            <?= $yoyChange ?>% vs last year
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Statistiques par Catégorie</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Catégorie</th>
                                        <th class="text-center">Articles</th>
                                        <th class="text-center">Stock Actuel</th>
                                        <th class="text-center">Entrées</th>
                                        <th class="text-center">Sorties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryStats as $stat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($stat['category']) ?></td>
                                        <td class="text-center"><?= number_format($stat['total_products']) ?></td>
                                        <td class="text-center"><?= number_format($stat['total_stock']) ?></td>
                                        <td class="text-center"><?= number_format($stat['total_entries']) ?></td>
                                        <td class="text-center"><?= number_format($stat['total_exits']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <!-- Most Consumed Products -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Articles les plus consommés</h5>
                        <div id="productsChart" class="chart-container"></div>
                    </div>
                </div>
            </div>

            <!-- Most Active Demanders -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Demandeurs les plus actifs</h5>
                        <div id="demandersChart" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Products Chart
        const productsOptions = {
            series: [{
                name: 'Quantité sortie',
                data: <?= json_encode($productQuantities) ?>
            }],
            chart: {
                type: 'bar',
                height: 400,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val.toLocaleString()
                }
            },
            xaxis: {
                categories: <?= json_encode($productNames) ?>,
                labels: {
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            colors: ['#2E93fA'],
            title: {
                text: 'Top 10 des articles les plus consommés',
                align: 'center',
                style: {
                    fontSize: '16px'
                }
            }
        };

        // Demanders Chart
        const demandersOptions = {
            series: [{
                name: 'Articles demandés',
                data: <?= json_encode($demanderQuantities) ?>
            }],
            chart: {
                type: 'bar',
                height: 400,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val.toLocaleString()
                }
            },
            xaxis: {
                categories: <?= json_encode($demanderNames) ?>,
                labels: {
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            colors: ['#FF4560'],
            title: {
                text: 'Top 10 des demandeurs les plus actifs',
                align: 'center',
                style: {
                    fontSize: '16px'
                }
            }
        };

        // Render the charts
        new ApexCharts(document.querySelector("#productsChart"), productsOptions).render();
        new ApexCharts(document.querySelector("#demandersChart"), demandersOptions).render();
    </script>
</body>
</html>