<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

// Custom PDF class
class BonDeSortiePDF extends \TCPDF {
    // Page header
    public function Header() {
        // Logo - centered at the top with very large size
        $pageWidth = $this->getPageWidth();
        $logoWidth = 100; // Much larger logo
        $logoX = ($pageWidth - $logoWidth) / 2; // Center the logo
        @$this->Image(__DIR__ . '/assets/images/logo.png', $logoX, 10, $logoWidth, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // No AZROU CENTER text - removed as requested
    }

    // Page footer - empty
    public function Footer() {
        // Empty footer
    }
}

$db = Connection::getInstance();
$success = $error = '';

/**
 * Generate PDF Report for Bon de Sortie based on date range and demander
 */
function generatePDF($exitItems, $demander, $startDate, $endDate, $bonNumber, $observation) {
    // Create new PDF document
    $pdf = new BonDeSortiePDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Stock Management System');
    $pdf->SetAuthor('Al Akhawayn University');
    $pdf->SetTitle('Bon de Sortie - ' . $bonNumber);
    $pdf->SetSubject('Bon de Sortie');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Date and Bon side by side - aligned exactly as in the image
    $pdf->SetY(40);
    $startObj = new DateTime($startDate);
    $endObj = new DateTime($endDate);
    
    // Format date text exactly as in the template
    $dateText = "Date: (" . $startObj->format('d/m/Y') . ") a (" . $endObj->format('d/m/Y') . ")";
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(100, 10, $dateText, 0, 0, 'L');
    $pdf->Cell(80, 10, 'BON N°: ' . $bonNumber, 0, 1, 'R');
    
    // Add the Centre title in French at the appropriate position
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Centre d\'Azrou pour le Développement Communautaire', 0, 1, 'C');
    
    // Add address and contact info
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Boulevard Prince My Abdellah Route de Khénifra  53100  Azrou, Maroc', 0, 1, 'C');
    $pdf->Cell(0, 6, '+212(0)5-35-86-23-38 // 05-35-86-26-96', 0, 1, 'C');
    
    $pdf->SetTextColor(0, 0, 255);
    $pdf->Cell(0, 6, 'www.aui.ma/en/azroucenter.html', 0, 1, 'C', false, 'http://www.aui.ma/en/azroucenter.html');
    $pdf->SetTextColor(0, 0, 0);
    
    // Create table with proper spacing after the header
    $pdf->Ln(5);
    
    // Table headers
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 10, 'Date', 1, 0, 'C');
    $pdf->Cell(70, 10, 'Article', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Observation', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Quantité', 1, 1, 'C');
    
    // Table rows
    $pdf->SetFont('helvetica', '', 10);
    
    // Get all exit details with dates for this demander within the date range
    global $db;
    $stmt = $db->prepare("
        SELECT 
            p.designation,
            se.exit_date,
            sei.quantity,
            se.observation
        FROM 
            stock_exits se
            JOIN stock_exit_items sei ON se.id = sei.exit_id
            JOIN products p ON sei.product_id = p.id
        WHERE 
            se.demander_id = ? AND
            DATE(se.exit_date) BETWEEN ? AND ?
        ORDER BY 
            se.exit_date, p.designation
    ");
    
    $demanderId = $_POST['demander_id'];
    $stmt->execute([$demanderId, $startDate, $endDate]);
    $detailedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($detailedItems)) {
        // Use the summary if no detailed items
        foreach ($exitItems as $item) {
            $pdf->Cell(30, 10, '', 1, 0, 'C'); // Empty date column
            $pdf->Cell(70, 10, $item['designation'], 1, 0, 'L');
            $pdf->Cell(50, 10, '', 1, 0, 'L'); // Empty observation column
            $pdf->Cell(30, 10, $item['total_quantity'], 1, 1, 'C');
        }
    } else {
        // Use detailed items showing individual dates and observations
        foreach ($detailedItems as $item) {
            $exitDate = new DateTime($item['exit_date']);
            $pdf->Cell(30, 10, $exitDate->format('d/m/Y'), 1, 0, 'C');
            $pdf->Cell(70, 10, $item['designation'], 1, 0, 'L');
            
            // Trim observation to fit the cell
            $obs = $item['observation'];
            if (strlen($obs) > 30) {
                $obs = substr($obs, 0, 27) . '...';
            }
            $pdf->Cell(50, 10, $obs, 1, 0, 'L');
            $pdf->Cell(30, 10, $item['quantity'], 1, 1, 'C');
        }
    }
    
    // Add demandeur and responsable info immediately after the last row
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(95, 10, 'Demandeur: ' . $demander['name'], 0, 0, 'L');
    $pdf->Cell(95, 10, 'Responsable du stock: N.HERRAR', 0, 1, 'R');
    
    // Add signature section with proper spacing
    $pdf->Ln(25);
    $pdf->Cell(90, 10, 'Manager:', 0, 0, 'L');
    
    // Get uploads directory path and ensure it exists
    $exportsDir = __DIR__ . '/exports';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }
    
    // Save PDF file
    $pdfFilename = 'exports/bon_sortie_' . $bonNumber . '.pdf';
    $pdf->Output(__DIR__ . '/' . $pdfFilename, 'F');
    
    // JavaScript to trigger file save dialog
    echo '<script>
        // Show save dialog
        let saveLink = document.createElement("a");
        saveLink.href = "' . $pdfFilename . '";
        saveLink.download = "Bon_de_sortie_' . $bonNumber . '.pdf";
        saveLink.click();
    </script>';
}

// Get filters from URL
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')); // Show last 7 days by default
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'all'; // 'all', 'entries', 'exits'
$demanderId = $_GET['demander_id'] ?? ''; // Filter by demander if specified

// Handle Bon de Sortie generation
if (isset($_POST['generate_pdf_report'])) {
    try {
        // Validate input
        if (empty($_POST['demander_id'])) {
            throw new Exception("Veuillez sélectionner un demandeur");
        }
        
        if (empty($_POST['start_date']) || empty($_POST['end_date'])) {
            throw new Exception("Veuillez sélectionner une période");
        }
        
        $selectedDemanderId = $_POST['demander_id'];
        $reportStartDate = $_POST['start_date'];
        $reportEndDate = $_POST['end_date'];
        $bonNumber = $_POST['bon_number'] ?? 'BON-' . date('YmdHis');
        $observation = $_POST['observation'] ?? '';
        
        // Get demander information
        $stmt = $db->prepare("SELECT name, department FROM demanders WHERE id = ?");
        $stmt->execute([$selectedDemanderId]);
        $demander = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demander) {
            throw new Exception("Demandeur non trouvé");
        }
        
        // Get all exit items for this demander within the date range
        $stmt = $db->prepare("
            SELECT 
                p.id as article_id,
                p.designation,
                SUM(sei.quantity) as total_quantity
            FROM 
                stock_exits se
                JOIN stock_exit_items sei ON se.id = sei.exit_id
                JOIN products p ON sei.product_id = p.id
            WHERE 
                se.demander_id = ? AND
                DATE(se.exit_date) BETWEEN ? AND ?
            GROUP BY 
                p.id, p.designation
            ORDER BY 
                p.designation
        ");
        
        $stmt->execute([$selectedDemanderId, $reportStartDate, $reportEndDate]);
        $exitItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($exitItems)) {
            throw new Exception("Aucun article trouvé pour cette période et ce demandeur");
        }
        
        // Generate the PDF
        generatePDF($exitItems, $demander, $reportStartDate, $reportEndDate, $bonNumber, $observation);
        
        $success = "Rapport généré avec succès";
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

try {
    // Get all demanders for the filter dropdown
    $demanders = $db->query("SELECT id, name, department FROM demanders ORDER BY name")->fetchAll();
    
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
                NULL as demander_id,
                NULL as observation,
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
                d.id as demander_id,
                se.observation,
                se.created_at
            FROM stock_exits se
            JOIN stock_exit_items sei ON se.id = sei.exit_id
            JOIN products p ON sei.product_id = p.id
            JOIN demanders d ON se.demander_id = d.id
            WHERE DATE(se.exit_date) BETWEEN :start_date AND :end_date
        ";
        
        // Add demander filter if selected
        if (!empty($demanderId)) {
            $query .= " AND se.demander_id = :demander_id";
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':demander_id' => $demanderId
            ];
        } else {
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
        }
        
        $query .= " ORDER BY se.created_at DESC";
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
                    NULL as demander_id,
                    NULL as observation,
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
                    d.id as demander_id,
                    se.observation,
                    se.created_at
                FROM stock_exits se
                JOIN stock_exit_items sei ON se.id = sei.exit_id
                JOIN products p ON sei.product_id = p.id
                JOIN demanders d ON se.demander_id = d.id
                WHERE DATE(se.exit_date) BETWEEN :start_date2 AND :end_date2
        ";
        
        // Add demander filter if selected
        if (!empty($demanderId)) {
            $query .= " AND se.demander_id = :demander_id";
        }
        
        $query .= ") as combined ORDER BY created_at DESC";
        
        if (!empty($demanderId)) {
            $params = [
                ':start_date1' => $startDate,
                ':end_date1' => $endDate,
                ':start_date2' => $startDate,
                ':end_date2' => $endDate,
                ':demander_id' => $demanderId
            ];
        } else {
            $params = [
                ':start_date1' => $startDate,
                ':end_date1' => $endDate,
                ':start_date2' => $startDate,
                ':end_date2' => $endDate
            ];
        }
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
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtrer les Transactions & Générer un Bon de Sortie</h5>
            </div>
            <div class="card-body">
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
                
                <form method="get" id="filter-form" class="row g-3">
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
                    <div class="col-md-3">
                        <label class="form-label">Demandeur</label>
                        <select name="demander_id" id="demander_select" class="form-select">
                            <option value="">Tous les demandeurs</option>
                            <?php foreach ($demanders as $demander): ?>
                                <option value="<?= $demander['id'] ?>" <?= $demanderId == $demander['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($demander['name']) ?> (<?= htmlspecialchars($demander['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary mt-4 w-100">
                            <i class="fas fa-filter me-2"></i>Filtrer
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="fas fa-file-pdf me-2"></i>Générer un Bon de Sortie</h5>
                        <p class="text-muted small">Utilisez les mêmes filtres de date et de demandeur ci-dessus pour générer un bon de sortie.</p>
                    </div>
                    
                    <!-- Hidden fields that copy values from the filter form -->
                    <input type="hidden" id="pdf_start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    <input type="hidden" id="pdf_end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    <input type="hidden" id="pdf_demander_id" name="demander_id" value="<?= $demanderId ?>">
                    
                    <div class="col-md-6">
                        <label class="form-label">Numéro de Bon</label>
                        <input type="text" name="bon_number" class="form-control" 
                               value="<?= 'BON-' . date('YmdHis') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observation (optionnel)</label>
                        <textarea class="form-control" name="observation" rows="1" 
                                  placeholder="Entrez une observation si nécessaire"></textarea>
                    </div>
                    <div class="col-12">
                        <?php if(empty($demanderId)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Veuillez sélectionner un demandeur spécifique pour générer un bon de sortie.
                            </div>
                        <?php else: ?>
                            <button type="submit" name="generate_pdf_report" class="btn btn-danger">
                                <i class="fas fa-file-pdf me-2"></i>Générer le Bon de Sortie
                            </button>
                        <?php endif; ?>
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
                                <th>Observation</th>
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
                                    <td>
                                        <?php if ($transaction['type'] === 'exit' && !empty($transaction['observation'])): ?>
                                            <span class="text-muted small"><?= htmlspecialchars($transaction['observation']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
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
    <script>
        // Add this JavaScript at the end of the file to sync the values between forms
        document.addEventListener('DOMContentLoaded', function() {
            // Elements from the filter form
            const startDateInput = document.querySelector('[name="start_date"]');
            const endDateInput = document.querySelector('[name="end_date"]');
            const demanderSelect = document.getElementById('demander_select');
            
            // Elements for the PDF generation (hidden fields)
            const pdfStartDate = document.getElementById('pdf_start_date');
            const pdfEndDate = document.getElementById('pdf_end_date');
            const pdfDemanderId = document.getElementById('pdf_demander_id');
            
            // Update hidden fields when the filter inputs change
            startDateInput.addEventListener('change', function() {
                pdfStartDate.value = this.value;
            });
            
            endDateInput.addEventListener('change', function() {
                pdfEndDate.value = this.value;
            });
            
            demanderSelect.addEventListener('change', function() {
                pdfDemanderId.value = this.value;
            });
        });
    </script>
</body>
</html>
