<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Handle article operations
$success = $error = '';

// Delete article
if (isset($_POST['delete_article']) && isset($_POST['article_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['article_id']]);
        $success = "Article supprimé avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Add new article
if (isset($_POST['add_article'])) {
    try {
        $stmt = $db->prepare("INSERT INTO products (designation, description, report_stock) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['designation'],
            $_POST['description'],
            $_POST['report_stock']
        ]);
        $success = "Article ajouté avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout: " . $e->getMessage();
    }
}

// Update article
if (isset($_POST['update_article'])) {
    try {
        $stmt = $db->prepare("UPDATE products SET designation = ?, description = ?, report_stock = ? WHERE id = ?");
        $stmt->execute([
            $_POST['designation'],
            $_POST['description'],
            $_POST['report_stock'],
            $_POST['article_id']
        ]);
        $success = "Article mis à jour avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Get all unique categories and their subcategories
$categoriesQuery = $db->query("
    SELECT DISTINCT 
        category,
        subcategory
    FROM products 
    WHERE category IS NOT NULL 
        AND category != '' 
        AND category NOT IN ('Désignation')
    ORDER BY category, subcategory
");

$categories = [];
$subcategories = [];
while ($row = $categoriesQuery->fetch(PDO::FETCH_ASSOC)) {
    if (!in_array($row['category'], $categories)) {
        $categories[] = $row['category'];
    }
    if (!isset($subcategories[$row['category']])) {
        $subcategories[$row['category']] = [];
    }
    if ($row['subcategory']) {
        $subcategories[$row['category']][] = $row['subcategory'];
    }
}

// Get selected filters
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
$selectedSubcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : 'all';

// Fetch products with optional section filter
$query = "
    SELECT 
        id,
        designation,
        description,
        report_stock,
        entre,
        sortie,
        current_stock,
        created_at,
        updated_at
    FROM products";

if ($selectedCategory !== 'all' && $selectedCategory) {
    $query .= " WHERE category = :category";
    if ($selectedSubcategory !== 'all' && $selectedSubcategory) {
        $query .= " AND subcategory = :subcategory";
    }
}

$query .= " ORDER BY description, designation";

$stmt = $db->prepare($query);
if ($selectedCategory !== 'all' && $selectedCategory) {
    $params = ['category' => $selectedCategory];
    if ($selectedSubcategory !== 'all' && $selectedSubcategory) {
        $params['subcategory'] = $selectedSubcategory;
    }
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$products = $stmt->fetchAll();

// Group products by section
$groupedProducts = [];
foreach ($products as $product) {
    $category = $product['category'] ?: 'Non catégorisé';
    if (!isset($groupedProducts[$category])) {
        $groupedProducts[$category] = [];
    }
    $groupedProducts[$category][] = $product;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État du Stock - Gestion des Stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .section-header {
            background-color: #f8f9fa !important;
            font-weight: bold;
            color: #006837;
        }
        .table-primary {
            --bs-table-bg: #e3f2fd;
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
                        <a class="nav-link active" href="view_stock.php"><i class="fas fa-boxes"></i> État Stock</a>
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
            <h2 class="mb-0">État du Stock</h2>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                <i class="fas fa-plus-circle"></i> Nouvel Article
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
                <!-- Section Filter -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <form id="sectionFilter" class="d-flex">
                            <div class="row">
                                <div class="col-md-5">
                                    <select name="category" id="categorySelect" class="form-select">
                                        <option value="all">Toutes les catégories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select name="subcategory" id="subcategorySelect" class="form-select" <?= $selectedCategory === 'all' ? 'disabled' : '' ?>>
                                        <option value="all">Toutes les sous-catégories</option>
                                        <?php if ($selectedCategory !== 'all' && isset($subcategories[$selectedCategory])): ?>
                                            <?php foreach ($subcategories[$selectedCategory] as $subcategory): ?>
                                                <?php if (!empty($subcategory)): ?>
                                                    <option value="<?= htmlspecialchars($subcategory) ?>" <?= $selectedSubcategory === $subcategory ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($subcategory) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                                </div>
                            </select>
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                        </form>
                    </div>
                </div>

                <table id="stockTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th>Description</th>
                            <th>Stock Année Précédente</th>
                            <th>Total Entrées</th>
                            <th>Total Sorties</th>
                            <th>Stock Actuel</th>
                            <th>Dernière Mise à Jour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupedProducts as $section => $sectionProducts): ?>
                            <!-- Section Header -->
                            <tr class="table-primary">
                                <th colspan="8" class="section-header">
                                    <i class="fas fa-folder-open me-2"></i><?= htmlspecialchars($section) ?>
                                    <span class="badge bg-secondary ms-2"><?= count($sectionProducts) ?> articles</span>
                                </th>
                            </tr>
                            <?php foreach ($sectionProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['designation']) ?></td>
                                <td><?= htmlspecialchars($product['description']) ?></td>
                                <td><?= $product['report_stock'] ?></td>
                                <td><?= $product['entre'] ?></td>
                                <td><?= $product['sortie'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $product['current_stock'] > 0 ? 'success' : 'danger' ?>">
                                        <?= $product['current_stock'] ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($product['updated_at'])) ?></td>
                                <td class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-primary edit-article" 
                                            data-id="<?= $product['id'] ?>"
                                            data-designation="<?= htmlspecialchars($product['designation']) ?>"
                                            data-description="<?= htmlspecialchars($product['description']) ?>"
                                            data-report-stock="<?= $product['report_stock'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline delete-form">
                                        <input type="hidden" name="article_id" value="<?= $product['id'] ?>">
                                        <button type="submit" name="delete_article" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Article Modal -->
    <div class="modal fade" id="addArticleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvel Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" class="form-control" id="designation" name="designation" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="report_stock" class="form-label">Stock Initial</label>
                            <input type="number" class="form-control" id="report_stock" name="report_stock" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_article" class="btn btn-success">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Article Modal -->
    <div class="modal fade" id="editArticleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="article_id" id="edit_article_id">
                        <div class="mb-3">
                            <label for="edit_designation" class="form-label">Désignation</label>
                            <input type="text" class="form-control" id="edit_designation" name="designation" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_report_stock" class="form-label">Stock Initial</label>
                            <input type="number" class="form-control" id="edit_report_stock" name="report_stock" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_article" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle category change
            $('#categorySelect').change(function() {
                var category = $(this).val();
                var subcategorySelect = $('#subcategorySelect');
                
                // Clear and disable subcategory select if 'all' is selected
                if (category === 'all') {
                    subcategorySelect.html('<option value="all">Toutes les sous-catégories</option>');
                    subcategorySelect.prop('disabled', true);
                    $('#sectionFilter').submit(); // Auto-submit on category change
                    return;
                }
                
                // Enable subcategory select
                subcategorySelect.prop('disabled', false);
                
                // Get subcategories for selected category via AJAX
                $.get('get_subcategories.php', { category: category }, function(data) {
                    var options = '<option value="all">Toutes les sous-catégories</option>';
                    data.forEach(function(subcategory) {
                        if (subcategory) { // Only add non-empty subcategories
                            options += `<option value="${subcategory}">${subcategory}</option>`;
                        }
                    });
                    subcategorySelect.html(options);
                    $('#sectionFilter').submit(); // Auto-submit on category change
                });
            });

            // Initialize DataTable
            $('#stockTable').DataTable({
                orderFixed: [[1, 'asc']],  // Always sort by section first
                rowGroup: {
                    dataSrc: 1  // Group by description (section) column
                },
                order: [[0, 'asc']],
                pageLength: 25,
                language: {
                    search: "Rechercher :",
                    lengthMenu: "Afficher _MENU_ éléments par page",
                    info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                    paginate: {
                        first: "Premier",
                        last: "Dernier",
                        next: "Suivant",
                        previous: "Précédent"
                    }
                }
            });

            // Handle edit button clicks
            $('.edit-article').click(function() {
                const id = $(this).data('id');
                const designation = $(this).data('designation');
                const description = $(this).data('description');
                const reportStock = $(this).data('report-stock');

                $('#edit_article_id').val(id);
                $('#edit_designation').val(designation);
                $('#edit_description').val(description);
                $('#edit_report_stock').val(reportStock);

                $('#editArticleModal').modal('show');
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
