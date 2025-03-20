<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();
$success = $error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add Supplier
        if (isset($_POST['add_supplier'])) {
            $stmt = $db->prepare("INSERT INTO suppliers (name, contact, phone, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['supplier_name'],
                $_POST['supplier_contact'],
                $_POST['supplier_phone'],
                $_POST['supplier_email']
            ]);
            $success = "Fournisseur ajouté avec succès";
        }
        
        // Add Demander
        if (isset($_POST['add_demander'])) {
            $stmt = $db->prepare("INSERT INTO demanders (name, department, contact) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['demander_name'],
                $_POST['department'],
                $_POST['demander_contact']
            ]);
            $success = "Demandeur ajouté avec succès";
        }

        // Delete Supplier
        if (isset($_POST['delete_supplier'])) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Set supplier_id to NULL in stock_entries
                $stmt = $db->prepare("UPDATE stock_entries SET supplier_id = NULL WHERE supplier_id = ?");
                $stmt->execute([$_POST['supplier_id']]);
                
                // Delete the supplier
                $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$_POST['supplier_id']]);
                
                // Commit transaction
                $db->commit();
                
                $success = "Fournisseur supprimé avec succès. Les entrées de stock associées ont été conservées avec un fournisseur non spécifié.";
            } catch (PDOException $e) {
                // Rollback in case of error
                $db->rollBack();
                $error = "Erreur lors de la suppression: " . $e->getMessage();
            }
        }

        // Delete Demander
        if (isset($_POST['delete_demander'])) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Set demander_id to NULL in stock_exits
                $stmt = $db->prepare("UPDATE stock_exits SET demander_id = NULL WHERE demander_id = ?");
                $stmt->execute([$_POST['demander_id']]);
                
                // Delete the demander
                $stmt = $db->prepare("DELETE FROM demanders WHERE id = ?");
                $stmt->execute([$_POST['demander_id']]);
                
                // Commit transaction
                $db->commit();
                
                $success = "Demandeur supprimé avec succès. Les bons de sortie associés ont été conservés avec un demandeur non spécifié.";
            } catch (PDOException $e) {
                // Rollback in case of error
                $db->rollBack();
                $error = "Erreur lors de la suppression: " . $e->getMessage();
            }
        }

        // Update Supplier
        if (isset($_POST['update_supplier'])) {
            $stmt = $db->prepare("UPDATE suppliers SET name = ?, contact = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->execute([
                $_POST['supplier_name'],
                $_POST['supplier_contact'],
                $_POST['supplier_phone'],
                $_POST['supplier_email'],
                $_POST['supplier_id']
            ]);
            $success = "Fournisseur mis à jour avec succès";
        }

        // Update Demander
        if (isset($_POST['update_demander'])) {
            $stmt = $db->prepare("UPDATE demanders SET name = ?, department = ?, contact = ? WHERE id = ?");
            $stmt->execute([
                $_POST['demander_name'],
                $_POST['department'],
                $_POST['demander_contact'],
                $_POST['demander_id']
            ]);
            $success = "Demandeur mis à jour avec succès";
        }

    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Fetch all suppliers and demanders
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$demanders = $db->query("SELECT * FROM demanders ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion - Stock Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .action-buttons {
            white-space: nowrap;
        }
        .table td {
            vertical-align: middle;
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
                        <a class="nav-link active" href="gestion.php"><i class="fas fa-cogs"></i> Gestion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-line"></i> Analytiques</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <!-- Suppliers Management -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Gestion des Fournisseurs</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="fas fa-plus"></i> Nouveau Fournisseur
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Contact</th>
                                        <th>Téléphone</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($supplier['name']) ?></td>
                                        <td><?= htmlspecialchars($supplier['contact']) ?></td>
                                        <td><?= htmlspecialchars($supplier['phone']) ?></td>
                                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-warning edit-supplier" 
                                                    data-supplier='<?= json_encode($supplier) ?>'
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editSupplierModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form class="d-inline" method="POST" onsubmit="return confirm('Êtes-vous sûr?');">
                                                <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">
                                                <button type="submit" name="delete_supplier" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demanders Management -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Gestion des Demandeurs</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDemanderModal">
                            <i class="fas fa-plus"></i> Nouveau Demandeur
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Département</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demanders as $demander): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($demander['name']) ?></td>
                                        <td><?= htmlspecialchars($demander['department']) ?></td>
                                        <td><?= htmlspecialchars($demander['contact']) ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-warning edit-demander" 
                                                    data-demander='<?= json_encode($demander) ?>'
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editDemanderModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form class="d-inline" method="POST" onsubmit="return confirm('Êtes-vous sûr?');">
                                                <input type="hidden" name="demander_id" value="<?= $demander['id'] ?>">
                                                <button type="submit" name="delete_demander" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un Fournisseur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" name="supplier_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="supplier_contact">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="supplier_phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="supplier_email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_supplier" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Demander Modal -->
    <div class="modal fade" id="addDemanderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un Demandeur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" name="demander_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Département</label>
                            <input type="text" class="form-control" name="department" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="demander_contact">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_demander" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Fournisseur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="supplier_id" id="edit_supplier_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" name="supplier_name" id="edit_supplier_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="supplier_contact" id="edit_supplier_contact">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="supplier_phone" id="edit_supplier_phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="supplier_email" id="edit_supplier_email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_supplier" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Demander Modal -->
    <div class="modal fade" id="editDemanderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Demandeur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="demander_id" id="edit_demander_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" name="demander_name" id="edit_demander_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Département</label>
                            <input type="text" class="form-control" name="department" id="edit_demander_department" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="demander_contact" id="edit_demander_contact">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_demander" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit supplier
        document.querySelectorAll('.edit-supplier').forEach(button => {
            button.addEventListener('click', function() {
                const supplier = JSON.parse(this.dataset.supplier);
                document.getElementById('edit_supplier_id').value = supplier.id;
                document.getElementById('edit_supplier_name').value = supplier.name;
                document.getElementById('edit_supplier_contact').value = supplier.contact;
                document.getElementById('edit_supplier_phone').value = supplier.phone;
                document.getElementById('edit_supplier_email').value = supplier.email;
            });
        });

        // Handle edit demander
        document.querySelectorAll('.edit-demander').forEach(button => {
            button.addEventListener('click', function() {
                const demander = JSON.parse(this.dataset.demander);
                document.getElementById('edit_demander_id').value = demander.id;
                document.getElementById('edit_demander_name').value = demander.name;
                document.getElementById('edit_demander_department').value = demander.department;
                document.getElementById('edit_demander_contact').value = demander.contact;
            });
        });
    </script>
</body>
</html> 