<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

// Generate Monthly Report
function generateMonthlyReport($db, $month, $year) {
    // Create new TCPDF instance
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Stock Management System');
    $pdf->SetAuthor('Al Akhawayn University');
    $pdf->SetTitle('Monthly Stock Report - ' . date('F Y', strtotime("$year-$month-01")));
    $pdf->SetSubject('Monthly Stock Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Add logo
    // Suppress PNG ICC profile warning
    $oldErrorReporting = error_reporting();
    error_reporting($oldErrorReporting & ~E_WARNING);
    $pdf->Image(__DIR__ . '/assets/images/logo.png', 10, 5, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    error_reporting($oldErrorReporting);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 20, 'Al Akhawayn University', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Centre d\'Azrou', 0, 1, 'R');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');
    
    // Add section title
    $pdf->SetY(40);
    $pdf->SetFont('helvetica', 'B', 12);
    $monthName = date('F', strtotime("$year-$month-01"));
    $pdf->Cell(0, 10, 'Rapport Mensuel: ' . $monthName . ' ' . $year, 0, 1, 'L');
    
    // Add summary subtitle
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Récapitulatif de mouvements du stock - ' . $monthName . ' ' . $year, 0, 1, 'L');
    $pdf->Ln(5);
    
    // Calculate the beginning and end of the month
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Fetch monthly stock data with filters
    $query = "
        SELECT 
            p.designation,
            p.report_stock as report,
            (SELECT COALESCE(SUM(se.quantity), 0) 
             FROM stock_entries se 
             WHERE se.product_id = p.id 
             AND DATE_FORMAT(se.entry_date, '%Y-%m') = ?) as entree,
            p.sortie as sortie,
            p.current_stock as stock
        FROM products p
        ORDER BY p.designation ASC
    ";
    
    $stmt = $db->prepare($query);
    $monthYear = "$year-$month";
    $stmt->bindParam(1, $monthYear);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add footer row with totals
    $totalReport = 0;
    $totalEntree = 0;
    $totalSortie = 0;
    $totalStock = 0;
    
    foreach($products as $product) {
        $totalReport += (int)$product['report'];
        $totalEntree += (int)$product['entree'];
        $totalSortie += (int)$product['sortie'];
        $totalStock += (int)$product['stock'];
    }
    
    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 8, 'Désignation', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Report', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Entrée', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Sortie', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Stock', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    
    foreach($products as $product) {
        $pdf->Cell(120, 7, $product['designation'], 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($product['report']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['entree']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['sortie']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['stock']), 1, 1, 'C');
    }
    
    // Add total row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(120, 7, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($totalReport), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalEntree), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalSortie), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalStock), 1, 1, 'C');
    
    // Output the PDF
    $fileName = 'monthly_report_' . $year . '_' . $month . '.pdf';
    $pdf->Output(__DIR__ . '/exports/' . $fileName, 'F');
    
    return $fileName;
}

// Generate Yearly Report
function generateYearlyReport($db, $year) {
    // Create new TCPDF instance
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Stock Management System');
    $pdf->SetAuthor('Al Akhawayn University');
    $pdf->SetTitle('Yearly Stock Report - ' . $year);
    $pdf->SetSubject('Yearly Stock Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Add logo
    // Suppress PNG ICC profile warning
    $oldErrorReporting = error_reporting();
    error_reporting($oldErrorReporting & ~E_WARNING);
    $pdf->Image(__DIR__ . '/assets/images/logo.png', 10, 5, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    error_reporting($oldErrorReporting);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 20, 'Al Akhawayn University', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Centre d\'Azrou', 0, 1, 'R');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');
    
    // Add section title
    $pdf->SetY(40);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Rapport Annuel: ' . $year, 0, 1, 'L');
    
    // Add summary subtitle
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Récapitulatif annuel de mouvements du stock - ' . $year, 0, 1, 'L');
    $pdf->Ln(5);
    
    // Fetch yearly stock data with filters
    $query = "
        SELECT 
            p.designation,
            p.report_stock as report,
            (SELECT COALESCE(SUM(se.quantity), 0) 
             FROM stock_entries se 
             WHERE se.product_id = p.id 
             AND YEAR(se.entry_date) = ?) as entree,
            p.sortie as sortie,
            p.current_stock as stock
        FROM products p
        ORDER BY p.designation ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $year);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add footer row with totals
    $totalReport = 0;
    $totalEntree = 0;
    $totalSortie = 0;
    $totalStock = 0;
    
    foreach($products as $product) {
        $totalReport += (int)$product['report'];
        $totalEntree += (int)$product['entree'];
        $totalSortie += (int)$product['sortie'];
        $totalStock += (int)$product['stock'];
    }
    
    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 8, 'Désignation', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Report', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Entrée', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Sortie', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Stock', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    
    foreach($products as $product) {
        $pdf->Cell(120, 7, $product['designation'], 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($product['report']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['entree']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['sortie']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($product['stock']), 1, 1, 'C');
    }
    
    // Add total row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(120, 7, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($totalReport), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalEntree), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalSortie), 1, 0, 'C');
    $pdf->Cell(25, 7, number_format($totalStock), 1, 1, 'C');
    
    // Output the PDF
    $fileName = 'yearly_report_' . $year . '.pdf';
    $pdf->Output(__DIR__ . '/exports/' . $fileName, 'F');
    
    return $fileName;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Connection::getInstance();
        
        // Ensure exports directory exists
        $exportsDir = __DIR__ . '/exports';
        if (!is_dir($exportsDir)) {
            mkdir($exportsDir, 0755, true);
        }
        
        // Generate monthly report
        if (isset($_POST['generate_monthly_report'])) {
            $month = $_POST['month'];
            $year = $_POST['year'];
            
            $fileName = generateMonthlyReport($db, $month, $year);
            $downloadUrl = 'exports/' . $fileName;
            
            // Redirect to download
            header("Location: $downloadUrl");
            exit;
        }
        
        // Generate yearly report
        if (isset($_POST['generate_yearly_report'])) {
            $year = $_POST['report_year'];
            
            $fileName = generateYearlyReport($db, $year);
            $downloadUrl = 'exports/' . $fileName;
            
            // Redirect to download
            header("Location: $downloadUrl");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error generating report: " . $e->getMessage();
    }
}

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .report-buttons {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .report-form {
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        <!-- Report Generation Buttons -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rapport Mensuel</h5>
                        <form method="post" class="report-form">
                            <select name="month" class="form-select" required>
                                <option value="">Sélectionner un mois</option>
                                <option value="01">Janvier</option>
                                <option value="02">Février</option>
                                <option value="03">Mars</option>
                                <option value="04">Avril</option>
                                <option value="05">Mai</option>
                                <option value="06">Juin</option>
                                <option value="07">Juillet</option>
                                <option value="08">Août</option>
                                <option value="09">Septembre</option>
                                <option value="10">Octobre</option>
                                <option value="11">Novembre</option>
                                <option value="12">Décembre</option>
                            </select>
                            <select name="year" class="form-select" required>
                                <option value="">Sélectionner une année</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                    echo "<option value=\"$y\">$y</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" name="generate_monthly_report" class="btn btn-primary">
                                <i class="fas fa-download"></i> Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rapport Annuel</h5>
                        <form method="post" class="report-form">
                            <select name="report_year" class="form-select" required>
                                <option value="">Sélectionner une année</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                    echo "<option value=\"$y\">$y</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" name="generate_yearly_report" class="btn btn-primary">
                                <i class="fas fa-download"></i> Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="col-12 mt-3">
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryStats as $stat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($stat['category'] ?: 'Non catégorisé') ?></td>
                                        <td class="text-center"><?= number_format($stat['total_products']) ?></td>
                                        <td class="text-center"><?= number_format($stat['total_stock']) ?></td>
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
                        <div class="chart-container">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Most Active Demanders -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Demandeurs les plus actifs</h5>
                        <div class="chart-container">
                            <canvas id="demandersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($productNames) ?>,
                datasets: [{
                    label: 'Quantité Sortie',
                    data: <?= json_encode($productQuantities) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Demanders Chart
        const demandersCtx = document.getElementById('demandersChart').getContext('2d');
        new Chart(demandersCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($demanderNames) ?>,
                datasets: [{
                    label: 'Nombre de Sorties',
                    data: <?= json_encode($demanderQuantities) ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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