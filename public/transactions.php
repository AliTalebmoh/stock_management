<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Get filters from URL
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')); // Show last 7 days by default
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'all'; // 'all', 'entries', 'exits'

try {
    // Build the query based on type filter
    if ($type === 'entries') {
        $query = "
            SELECT 
                'entry' as type,
                se.entry_date as date,
                p.designation as product,
                se.quantity,
                s.name as source_name,
                NULL as requester_name,
                se.created_at
            FROM stock_entries se
            JOIN products p ON se.product_id = p.id
            JOIN suppliers s ON se.supplier_id = s.id
            WHERE DATE(se.entry_date) BETWEEN :start_date AND :end_date
            ORDER BY se.created_at DESC
        ";
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];
    } elseif ($type === 'exits') {
        $query = "
            SELECT 
                'exit' as type,
                se.exit_date as date,
                p.designation as product,
                sei.quantity,
                NULL as source_name,
                d.name as requester_name,
                se.created_at
            FROM stock_exits se
            JOIN stock_exit_items sei ON se.id = sei.exit_id
            JOIN products p ON sei.product_id = p.id
            JOIN demanders d ON se.demander_id = d.id
            WHERE DATE(se.exit_date) BETWEEN :start_date AND :end_date
            ORDER BY se.created_at DESC
        ";
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];
    } else {
        $query = "
            SELECT * FROM (
                SELECT 
                    'entry' as type,
                    se.entry_date as date,
                    p.designation as product,
                    se.quantity,
                    s.name as source_name,
                    NULL as requester_name,
                    se.created_at
                FROM stock_entries se
                JOIN products p ON se.product_id = p.id
                JOIN suppliers s ON se.supplier_id = s.id
                WHERE DATE(se.entry_date) BETWEEN :start_date1 AND :end_date1
                
                UNION ALL
                
                SELECT 
                    'exit' as type,
                    se.exit_date as date,
                    p.designation as product,
                    sei.quantity,
                    NULL as source_name,
                    d.name as requester_name,
                    se.created_at
                FROM stock_exits se
                JOIN stock_exit_items sei ON se.id = sei.exit_id
                JOIN products p ON sei.product_id = p.id
                JOIN demanders d ON se.demander_id = d.id
                WHERE DATE(se.exit_date) BETWEEN :start_date2 AND :end_date2
            ) as combined
            ORDER BY created_at DESC
        ";
        $params = [
            ':start_date1' => $startDate,
            ':end_date1' => $endDate,
            ':start_date2' => $startDate,
            ':end_date2' => $endDate
        ];
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Transactions - Gestion des Stocks</title>
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
        .transaction-entry {
            border-left: 4px solid #28a745;
        }
        .transaction-exit {
            border-left: 4px solid #dc3545;
        }
        .table th {
            background-color: #f8f9fa;
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-history"></i> Historique
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Historique des Transactions</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item active">Historique</li>
                </ol>
            </nav>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date de début</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date de fin</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Tous</option>
                            <option value="entries" <?= $type === 'entries' ? 'selected' : '' ?>>Entrées</option>
                            <option value="exits" <?= $type === 'exits' ? 'selected' : '' ?>>Sorties</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Article</th>
                                <th>Quantité</th>
                                <th>Source/Demandeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="<?= $transaction['type'] === 'entry' ? 'transaction-entry' : 'transaction-exit' ?>">
                                    <td><?= date('d/m/Y', strtotime($transaction['date'])) ?></td>
                                    <td>
                                        <?php if ($transaction['type'] === 'entry'): ?>
                                            <span class="badge bg-success">Entrée</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Sortie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['product']) ?></td>
                                    <td><?= $transaction['quantity'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($transaction['type'] === 'entry' ? 
                                            $transaction['source_name'] : $transaction['requester_name']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-info-circle me-2"></i>Aucune transaction trouvée
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
