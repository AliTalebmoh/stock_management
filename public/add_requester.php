<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Handle requester operations
$success = $error = '';

// Add new requester
if (isset($_POST['add_requester'])) {
    try {
        $stmt = $db->prepare("INSERT INTO requesters (name, department, contact, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['department'],
            $_POST['contact'],
            $_POST['email'],
            $_POST['role']
        ]);
        $success = "Demandeur ajouté avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout: " . $e->getMessage();
    }
}

// Update requester
if (isset($_POST['update_requester'])) {
    try {
        $stmt = $db->prepare("UPDATE requesters SET name = ?, department = ?, contact = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['department'],
            $_POST['contact'],
            $_POST['email'],
            $_POST['role'],
            $_POST['requester_id']
        ]);
        $success = "Demandeur mis à jour avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Delete requester
if (isset($_POST['delete_requester'])) {
    try {
        // Check if requester has any associated requests
        $stmt = $db->prepare("SELECT COUNT(*) FROM stock_requests WHERE requester_id = ?");
        $stmt->execute([$_POST['requester_id']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = "Impossible de supprimer ce demandeur car il a des demandes associées";
        } else {
            $stmt = $db->prepare("DELETE FROM requesters WHERE id = ?");
            $stmt->execute([$_POST['requester_id']]);
            $success = "Demandeur supprimé avec succès";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Get all requesters
$stmt = $db->query("SELECT * FROM requesters ORDER BY name");
$requesters = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un demandeur - Gestion des Stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-line"></i> Analytiques</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="add_requester.php"><i class="fas fa-user-plus"></i> Ajouter un demandeur</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Ajouter un demandeur</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequesterModal">
                <i class="fas fa-user-plus"></i> Nouveau Demandeur
            </button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table id="requestersTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Département</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Demandes</th>
                            <th>Date d'ajout</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requesters as $requester): ?>
                            <tr>
                                <td><?= htmlspecialchars($requester['name']) ?></td>
                                <td><?= htmlspecialchars($requester['department']) ?></td>
                                <td><?= htmlspecialchars($requester['contact']) ?></td>
                                <td><?= htmlspecialchars($requester['email']) ?></td>
                                <td><?= htmlspecialchars($requester['role']) ?></td>
                                <td>
                                    <?php
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM stock_requests WHERE requester_id = ?");
                                    $stmt->execute([$requester['id']]);
                                    $requestCount = $stmt->fetchColumn();
                                    echo "<span class='badge bg-info'>$requestCount</span>";
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($requester['created_at']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-primary edit-requester" 
                                                data-id="<?= $requester['id'] ?>"
                                                data-name="<?= htmlspecialchars($requester['name']) ?>"
                                                data-department="<?= htmlspecialchars($requester['department']) ?>"
                                                data-contact="<?= htmlspecialchars($requester['contact']) ?>"
                                                data-email="<?= htmlspecialchars($requester['email']) ?>"
                                                data-role="<?= htmlspecialchars($requester['role']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#editRequesterModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger delete-requester"
                                                data-id="<?= $requester['id'] ?>"
                                                data-name="<?= htmlspecialchars($requester['name']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteRequesterModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Requester Modal -->
    <div class="modal fade" id="addRequesterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un nouveau demandeur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom complet</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Département</label>
                            <input type="text" class="form-control" id="department" name="department" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Étudiant">Étudiant</option>
                                <option value="Personnel">Personnel</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_requester" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <!-- Edit Requester Modal -->
    <div class="modal fade" id="editRequesterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le demandeur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="requester_id" id="edit_requester_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nom complet</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_department" class="form-label">Département</label>
                            <input type="text" class="form-control" id="edit_department" name="department" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_contact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="edit_contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Rôle</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Étudiant">Étudiant</option>
                                <option value="Personnel">Personnel</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_requester" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Requester Modal -->
    <div class="modal fade" id="deleteRequesterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="requester_id" id="delete_requester_id">
                    <div class="modal-body">
                        <p>Voulez-vous vraiment supprimer le demandeur <strong><span id="delete_requester_name"></span></strong>?</p>
                        <p class="text-danger">Cette action est irréversible.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="delete_requester" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#requestersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json'
                },
                order: [[0, 'asc']] // Sort by name by default
            });

            // Handle edit requester button click
            $('.edit-requester').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const department = $(this).data('department');
                const contact = $(this).data('contact');
                const email = $(this).data('email');
                const role = $(this).data('role');

                $('#edit_requester_id').val(id);
                $('#edit_name').val(name);
                $('#edit_department').val(department);
                $('#edit_contact').val(contact);
                $('#edit_email').val(email);
                $('#edit_role').val(role);
            });

            // Handle delete requester button click
            $('.delete-requester').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                $('#delete_requester_id').val(id);
                $('#delete_requester_name').text(name);
            });
        });
    </script>
</body>
</html>
