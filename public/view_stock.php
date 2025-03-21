<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database\Connection;

$db = Connection::getInstance();

// Handle article operations
$success = $error = '';

// Handle stock request
if (isset($_POST['request_article'])) {
    try {
        // First check if there's enough stock
        $stmt = $db->prepare("SELECT current_stock FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $currentStock = $stmt->fetchColumn();

        if ($currentStock >= $_POST['quantity']) {
            // Create the request
            $stmt = $db->prepare("INSERT INTO stock_requests (requester_id, product_id, quantity, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['requester_id'],
                $_POST['product_id'],
                $_POST['quantity'],
                $_POST['notes']
            ]);

            $success = "Demande créée avec succès";
        } else {
            $error = "Stock insuffisant pour cette demande";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la création de la demande: " . $e->getMessage();
    }
}

// Delete article
if (isset($_POST['delete_article']) && isset($_POST['article_id'])) {
    try {
        // First check if the product has any dependencies
        $articleId = $_POST['article_id'];
        
        // Check stock entries
        $stmt = $db->prepare("SELECT COUNT(*) FROM stock_entries WHERE product_id = ?");
        $stmt->execute([$articleId]);
        $entriesCount = $stmt->fetchColumn();
        
        // Check stock exits
        $stmt = $db->prepare("SELECT COUNT(*) FROM stock_exit_items WHERE product_id = ?");
        $stmt->execute([$articleId]);
        $exitsCount = $stmt->fetchColumn();
        
        if ($entriesCount > 0 || $exitsCount > 0) {
            // Product has dependencies, cannot delete
            $error = "Impossible de supprimer cet article car il est utilisé dans des entrées ou sorties de stock. Utilisez la suppression forcée pour les articles de test.";
        } else {
            // No dependencies, safe to delete
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$articleId]);
            $success = "Article supprimé avec succès";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Force delete article (including related records)
if (isset($_POST['force_delete_article']) && isset($_POST['article_id'])) {
    try {
        $db->beginTransaction();
        $articleId = $_POST['article_id'];
        
        // Delete related stock_exit_items
        $stmt = $db->prepare("DELETE FROM stock_exit_items WHERE product_id = ?");
        $stmt->execute([$articleId]);
        
        // Delete related stock_entries
        $stmt = $db->prepare("DELETE FROM stock_entries WHERE product_id = ?");
        $stmt->execute([$articleId]);
        
        // Now delete the product itself
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$articleId]);
        
        $db->commit();
        $success = "Article et ses données associées supprimés avec succès";
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Erreur lors de la suppression forcée: " . $e->getMessage();
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

// Get all unique categories
$categoriesQuery = $db->query("
    SELECT DISTINCT category_type as category
    FROM products 
    WHERE category_type != 'Non classé'
    ORDER BY category_type
");

$categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);

// Get selected category from filter
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

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
        updated_at,
        category_type
    FROM products";

if ($selectedCategory !== 'all' && $selectedCategory) {
    $query .= " WHERE category_type = :category";
}

$query .= " ORDER BY description, designation";

$stmt = $db->prepare($query);
if ($selectedCategory !== 'all' && $selectedCategory) {
    $stmt->execute(['category' => $selectedCategory]);
} else {
    $stmt->execute();
}

$products = $stmt->fetchAll();

// Group products by section
$groupedProducts = [];
foreach ($products as $product) {
    $category = $product['category_type'] ?: 'Non catégorisé';
    if (!isset($groupedProducts[$category])) {
        $groupedProducts[$category] = [];
    }
    $groupedProducts[$category][] = $product;
}

// In your query where you display stock entries, use COALESCE
$stockEntries = $db->query("
    SELECT 
        se.*,
        p.designation as product_name,
        COALESCE(s.name, 'Fournisseur non spécifié') as supplier_name
    FROM stock_entries se
    JOIN products p ON se.product_id = p.id
    LEFT JOIN suppliers s ON se.supplier_id = s.id
    ORDER BY se.entry_date DESC
")->fetchAll();
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
                    <li class="nav-item">
                        <a class="nav-link" href="add_requester.php"><i class="fas fa-user-plus"></i> Ajouter un demandeur</a>
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
                <div class="card mb-3 border-0 bg-light">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="categorySelect" class="form-label mb-1">Catégorie</label>
                                <select name="category" id="categorySelect" class="form-select shadow-sm">
                                    <option value="all">Toutes les catégories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category) ?>">
                                            <?= htmlspecialchars($category) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="searchInput" class="form-label mb-1">Rechercher</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" id="searchInput" class="form-control" 
                                           placeholder="Rechercher un article par désignation..." 
                                           aria-label="Search">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <table id="stockTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th class="text-center">Report</th>
                            <th class="text-center">Entrées</th>
                            <th class="text-center">Sorties</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Dernier Mise à Jour</th>
                            <th class="text-center" style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Content will be loaded dynamically via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Request Article Modal -->
    <div class="modal fade" id="requestArticleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Demander un article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="product_id" id="request_product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Article</label>
                            <input type="text" class="form-control" id="request_designation" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock disponible</label>
                            <input type="text" class="form-control" id="request_current_stock" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="requester_id" class="form-label">Demandeur</label>
                            <select class="form-select" id="requester_id" name="requester_id" required>
                                <option value="">Sélectionner un demandeur</option>
                                <?php
                                $requesters = $db->query("SELECT id, name, department FROM requesters ORDER BY name")->fetchAll();
                                foreach ($requesters as $requester) {
                                    echo "<option value=\"{$requester['id']}\">{$requester['name']} ({$requester['department']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantité</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" required min="1">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="request_article" class="btn btn-success">Demander</button>
                    </div>
                </form>
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

    <!-- Delete Article Modal -->
    <div class="modal fade" id="deleteArticleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Supprimer l'Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="article_id" id="delete_article_id">
                        <p>Êtes-vous sûr de vouloir supprimer l'article suivant?</p>
                        <p class="fw-bold text-danger" id="delete_article_name"></p>
                        <p class="small text-muted">Cette action est irréversible.</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Si cet article a été utilisé dans des entrées ou sorties de stock, il ne pourra pas être supprimé normalement.
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Attention:</strong> La suppression forcée supprimera l'article ET TOUTES ses entrées/sorties associées. À utiliser uniquement pour les articles de test.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="delete_article" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Supprimer
                        </button>
                        <button type="submit" name="force_delete_article" class="btn btn-danger" onclick="return confirm('ATTENTION: Cette action supprimera définitivement toutes les données associées à cet article. Êtes-vous absolument sûr de vouloir continuer?')">
                            <i class="fas fa-skull-crossbones me-1"></i> Suppression Forcée
                        </button>
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
            // Add loading indicator
            function showLoading() {
                $('#stockTable tbody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <div class="mt-2">Chargement des articles...</div>
                        </td>
                    </tr>
                `);
            }

            function fetchArticles() {
                showLoading();
                const category = $('#categorySelect').val();
                const search = $('#searchInput').val();
                
                $.ajax({
                    url: 'fetch_articles.php',
                    method: 'GET',
                    data: {
                        category: category,
                        search: search
                    },
                    success: function(data) {
                        // Clear existing table content
                        $('#stockTable tbody').empty();
                        
                        if (Object.keys(data).length === 0) {
                            $('#stockTable tbody').html(`
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-search fa-2x text-muted mb-3"></i>
                                        <div class="text-muted">Aucun article trouvé</div>
                                    </td>
                                </tr>
                            `);
                            return;
                        }
                        
                        // Populate table with new data
                        Object.entries(data).forEach(([category, products]) => {
                            // Add category header
                            $('#stockTable tbody').append(`
                                <tr class="table-primary">
                                    <th colspan="7" class="section-header">
                                        <i class="fas fa-folder-open me-2"></i>${category}
                                        <span class="badge bg-secondary ms-2">${products.length} articles</span>
                                    </th>
                                </tr>
                            `);
                            
                            // Add products
                            products.forEach(product => {
                                const designation = product.designation.replace(/"/g, '&quot;');
                                const description = (product.description || '').replace(/"/g, '&quot;');
                                
                                $('#stockTable tbody').append(`
                                    <tr>
                                        <td class="align-middle">${designation}</td>
                                        <td class="text-center align-middle">${product.report_stock}</td>
                                        <td class="text-center align-middle">${product.entre}</td>
                                        <td class="text-center align-middle">${product.sortie}</td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-${product.current_stock > 0 ? 'success' : 'danger'} px-3 py-2">
                                                ${product.current_stock}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle">
                                            ${new Date(product.updated_at).toLocaleDateString('fr-FR', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-article" 
                                                        data-id="${product.id}"
                                                        data-designation="${designation}"
                                                        data-description="${description}"
                                                        data-report-stock="${product.report_stock}"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editArticleModal"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-article"
                                                        data-id="${product.id}"
                                                        data-designation="${designation}"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteArticleModal"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `);
                            });
                        });
                        
                        // Reattach event handlers for edit buttons
                        attachEditHandlers();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching articles:', error);
                        $('#stockTable tbody').html(`
                            <tr>
                                <td colspan="7" class="text-center py-4 text-danger">
                                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                                    <div>Erreur lors du chargement des articles. Veuillez réessayer.</div>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Improved debounce function
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Initial load
            fetchArticles();

            // Handle category select change
            $('#categorySelect').on('change', fetchArticles);

            // Handle search input with improved debounce
            const debouncedFetch = debounce(fetchArticles, 300);
            $('#searchInput').on('input', debouncedFetch);

            // Function to attach edit handlers
            function attachEditHandlers() {
                $('.edit-article').click(function() {
                    const id = $(this).data('id');
                    const designation = $(this).data('designation');
                    const description = $(this).data('description');
                    const reportStock = $(this).data('report-stock');

                    $('#edit_article_id').val(id);
                    $('#edit_designation').val(designation);
                    $('#edit_description').val(description);
                    $('#edit_report_stock').val(reportStock);
                });
                
                // Set up delete button handlers
                $('.delete-article').click(function() {
                    const id = $(this).data('id');
                    const designation = $(this).data('designation');
                    
                    $('#delete_article_id').val(id);
                    $('#delete_article_name').text(designation);
                });
            }

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
