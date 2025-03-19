<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

try {
    $db = Connection::getInstance();

    // Get top products by category
    $topProductsByCategory = $db->query("
        SELECT 
            p.designation,
            COALESCE(p.category_type, 'Non catégorisé') as category_type,
            p.sortie as total_quantity
        FROM products p
        WHERE p.sortie > 0
        ORDER BY p.category_type, p.sortie DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Group by category
    $categorizedProducts = [];
    foreach ($topProductsByCategory as $product) {
        $category = $product['category_type'];
        if (!isset($categorizedProducts[$category])) {
            $categorizedProducts[$category] = [];
        }
        $categorizedProducts[$category][] = $product;
    }

    // If no categories exist, create a default one
    if (empty($categorizedProducts)) {
        $categorizedProducts['Non catégorisé'] = [];
    }

    // Get top 10 overall
    $topProducts = array_slice($topProductsByCategory, 0, 10);

    // Get top 10 most active requesters
    $topRequesters = $db->query("
        SELECT 
            r.name,
            r.role,
            COUNT(DISTINCT sr.id) as total_requests,
            COALESCE(SUM(sr.quantity), 0) as total_items
        FROM requesters r
        LEFT JOIN stock_requests sr ON r.id = sr.requester_id
        GROUP BY r.id, r.name, r.role
        HAVING total_requests > 0
        ORDER BY total_items DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // If no requesters exist, initialize empty array
    if (empty($topRequesters)) {
        $topRequesters = [];    
    }

    // Get monthly consumption trend for the current year by category
    $monthlyTrend = $db->query("
        SELECT 
            DATE_FORMAT(COALESCE(sr.request_date, CURRENT_DATE), '%Y-%m') as month,
            COALESCE(p.category_type, 'Non catégorisé') as category_type,
            COALESCE(SUM(sr.quantity), 0) as total_quantity
        FROM products p
        LEFT JOIN stock_requests sr ON p.id = sr.product_id
        WHERE YEAR(COALESCE(sr.request_date, CURRENT_DATE)) = YEAR(CURRENT_DATE)
        GROUP BY DATE_FORMAT(COALESCE(sr.request_date, CURRENT_DATE), '%Y-%m'), p.category_type
        ORDER BY month, category_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // If no monthly data exists, create default data
    if (empty($monthlyTrend)) {
        $currentMonth = date('Y-m');
        $monthlyTrend = [[
            'month' => $currentMonth,
            'category_type' => 'Non catégorisé',
            'total_quantity' => 0
        ]];
    }

    // Get overall statistics with error handling
    $overallStats = $db->query("
        SELECT
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(p.current_stock), 0) as total_stock,
            COALESCE(SUM(p.entre), 0) as total_entries,
            COALESCE(SUM(p.sortie), 0) as total_exits,
            COUNT(DISTINCT p.category_type) as total_categories,
            COUNT(DISTINCT CASE WHEN p.current_stock = 0 THEN p.id END) as out_of_stock
        FROM products p
    ")->fetch(PDO::FETCH_ASSOC);

    // Get category statistics with error handling
    $categoryStats = $db->query("
        SELECT
            COALESCE(p.category_type, 'Non catégorisé') as category,
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(p.current_stock), 0) as total_stock,
            COALESCE(SUM(p.entre), 0) as total_entries,
            COALESCE(SUM(p.sortie), 0) as total_exits
        FROM products p
        GROUP BY p.category_type
        ORDER BY total_products DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Log the error
    error_log("Analytics Error: " . $e->getMessage());
    
    // Initialize empty data structures
    $categorizedProducts = ['Non catégorisé' => []];
    $topProducts = [];
    $topRequesters = [];
    $monthlyTrend = [['month' => date('Y-m'), 'category_type' => 'Non catégorisé', 'total_quantity' => 0]];
    $overallStats = [
        'total_products' => 0,
        'total_stock' => 0,
        'total_entries' => 0,
        'total_exits' => 0,
        'total_categories' => 0,
        'out_of_stock' => 0
    ];
    $categoryStats = [[
        'category' => 'Non catégorisé',
        'total_products' => 0,
        'total_stock' => 0,
        'total_entries' => 0,
        'total_exits' => 0
    ]];
}

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
        SUM(p.sortie) as total_exits,
        COUNT(DISTINCT p.category_type) as total_categories,
        COUNT(DISTINCT CASE WHEN p.current_stock = 0 THEN p.id END) as out_of_stock
    FROM products p
")->fetch();

// Get category statistics
$categoryStats = $db->query("
    SELECT
        COALESCE(p.category_type, 'Non catégorisé') as category,
        COUNT(DISTINCT p.id) as total_products,
        SUM(p.current_stock) as total_stock,
        SUM(p.entre) as total_entries,
        SUM(p.sortie) as total_exits
    FROM products p
    GROUP BY p.category_type
    ORDER BY total_products DESC
")->fetchAll();

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
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Catégories</h6>
                        <p class="card-text h3 text-primary"><?= number_format($overallStats['total_categories']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Articles</h6>
                        <p class="card-text h3 text-success"><?= number_format($overallStats['total_products']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Stock épuisé</h6>
                        <p class="card-text h3 text-danger"><?= number_format($overallStats['out_of_stock']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Stock Actuel</h6>
                        <p class="card-text h3"><?= number_format($overallStats['total_stock']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Entrées</h6>
                        <p class="card-text h3 text-info"><?= number_format($overallStats['total_entries']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h6 class="card-title">Sorties</h6>
                        <p class="card-text h3 text-warning"><?= number_format($overallStats['total_exits']) ?></p>
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
            <!-- Most Consumed Products by Category -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Articles les Plus Consommés par Catégorie</h5>
                        <div class="mb-3">
                            <select id="categorySelect" class="form-select">
                                <?php foreach ($categorizedProducts as $category => $products): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Most Active Requesters -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Demandeurs les Plus Actifs</h5>
                        <canvas id="requestersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Consumption Trend -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tendance de Consommation Mensuelle par Catégorie (<?= date('Y') ?>)</h5>
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize products chart with first category
        const firstCategory = Object.keys(<?= json_encode($categorizedProducts) ?>)[0];
        const firstProducts = <?= json_encode(array_map(function($p) {
            return [
                'designation' => $p['designation'],
                'total_quantity' => $p['total_quantity']
            ];
        }, array_slice($categorizedProducts[array_key_first($categorizedProducts)], 0, 10))) ?>;

        const productsChart = new Chart(document.getElementById('productsChart'), {
            type: 'bar',
            data: {
                labels: firstProducts.map(p => p.designation),
                datasets: [{
                    label: 'Quantité Consommée',
                    data: firstProducts.map(p => p.total_quantity),
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: firstCategory
                    }
                }
            }
        });

        // Update chart when category changes
        document.getElementById('categorySelect').addEventListener('change', function(e) {
            const category = e.target.value;
            const products = <?= json_encode($categorizedProducts) ?>[category];
            const chartData = products.slice(0, 10).map(p => ({
                designation: p.designation,
                total_quantity: p.total_quantity
            }));
            
            productsChart.data.labels = chartData.map(p => p.designation);
            productsChart.data.datasets[0].data = chartData.map(p => p.total_quantity);
            productsChart.options.plugins.title.text = category;
            productsChart.update();
        });

        // Most Active Requesters Chart
        const requestersChart = new Chart(document.getElementById('requestersChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($r) { return $r['name'] . ' (' . $r['role'] . ')'; }, $topRequesters)) ?>,
                datasets: [{
                    label: 'Demandes',
                    data: <?= json_encode(array_map(function($r) { return $r['total_requests']; }, $topRequesters)) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }, {
                    label: 'Articles',
                    data: <?= json_encode(array_map(function($r) { return $r['total_items']; }, $topRequesters)) ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.5)',
                    borderColor: 'rgba(23, 162, 184, 1)',
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

        // Monthly Trend Chart by Category
        const trendChart = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_values(array_unique(array_column($monthlyTrend, 'month')))) ?>,
                datasets: [
                    <?php 
                    $categories = array_unique(array_column($monthlyTrend, 'category_type'));
                    $colors = ['rgba(0, 123, 255, 1)', 'rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)', 'rgba(255, 193, 7, 1)', 'rgba(23, 162, 184, 1)'];
                    foreach ($categories as $i => $category): 
                        $color = $colors[$i % count($colors)];
                    ?>
                    {
                        label: '<?= $category ?: "Non catégorisé" ?>',
                        data: <?= json_encode(array_map(function($trend) use ($category) {
                            return $trend['category_type'] === $category ? $trend['total_quantity'] : 0;
                        }, $monthlyTrend)) ?>,
                        borderColor: '<?= $color ?>',
                        backgroundColor: '<?= str_replace('1)', '0.1)', $color) ?>',
                        borderWidth: 2,
                        fill: true
                    },
                    <?php endforeach; ?>
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: true
                    },
                    x: {
                        ticks: {
                            callback: function(value, index, values) {
                                const date = new Date(this.getLabelForValue(value));
                                return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                            }
                        }
                    }
                }
            }
        });

    </script>
</body>
</html>