<?php
// Fix the include path
require_once 'config/functions.php';
requireAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'reports';
$user_id = $_SESSION['user_id'];

// ===== PDF Download Handler for Admin =====
if ($action == 'download_pdf' && isset($_GET['id'])) {
    $report_id = $_GET['id'];
    
    // Check if PDF generator exists
    if (!file_exists('config/pdf_generator.php')) {
        redirect('admin.php?action=reports&error=pdf_missing');
    }
    
    require_once 'config/pdf_generator.php';
    
    $pdf = generateReportPDF($report_id, 'admin');
    
    if ($pdf) {
        $filename = 'Report_' . $report_id . '_' . date('Y-m-d') . '.pdf';
        $pdf->stream($filename, array('Attachment' => 0));
        exit();
    } else {
        redirect('admin.php?action=reports&error=pdf_failed');
    }
}

// ===== Download All Reports as PDF =====
if ($action == 'download_all_pdf') {
    // Check if PDF generator exists
    if (!file_exists('config/pdf_generator.php')) {
        redirect('admin.php?action=reports&error=pdf_missing');
    }
    
    require_once 'config/pdf_generator.php';
    
    $pdf = generateAllReportsPDF();
    
    if ($pdf) {
        $filename = 'All_Reports_' . date('Y-m-d') . '.pdf';
        $pdf->stream($filename, array('Attachment' => 0));
        exit();
    } else {
        redirect('admin.php?action=reports&error=pdf_failed');
    }
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="content-wrapper">
                <?php if(isset($_GET['error'])): ?>
                    <?php if($_GET['error'] == 'pdf_missing'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> PDF generator is not available. Please install DOMPDF: <code>composer require dompdf/dompdf</code>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif($_GET['error'] == 'pdf_failed'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> Failed to generate PDF. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> An error occurred.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($action == 'reports'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> Manage All Reports</h5>
                            <div>
                                <a href="?action=download_all_pdf" target="_blank" class="btn btn-success btn-sm me-2">
                                    <i class="fas fa-file-pdf"></i> Download All PDF
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Reporter</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        global $pdo;
                                        $stmt = $pdo->prepare("SELECT r.*, u.full_name as reporter_name 
                                                               FROM reports r 
                                                               LEFT JOIN users u ON r.user_id = u.id 
                                                               ORDER BY r.created_at DESC");
                                        $stmt->execute();
                                        $reports = $stmt->fetchAll();
                                        ?>
                                        <?php foreach($reports as $report): ?>
                                        <tr>
                                            <td>#<?php echo $report['id']; ?></td>
                                            <td><?php echo $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['reporter_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['title']); ?></td>
                                            <td><?php echo $report['category']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $report['status'] == 'Resolved' ? 'success' : 
                                                        ($report['status'] == 'Pending' ? 'warning' : 
                                                        ($report['status'] == 'Under Review' ? 'info' : 
                                                        ($report['status'] == 'Assigned' ? 'primary' : 
                                                        ($report['status'] == 'Investigating' ? 'secondary' : 'dark')))); 
                                                ?>">
                                                    <?php echo $report['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="reporting.php?action=details&id=<?php echo $report['id']; ?>" class="btn btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?action=download_pdf&id=<?php echo $report['id']; ?>" target="_blank" class="btn btn-success">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($action == 'responders'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Manage Responders</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResponderModal">
                                <i class="fas fa-plus"></i> Add Responder
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Organization</th>
                                            <th>Role</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        global $pdo;
                                        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'responder' ORDER BY full_name");
                                        $stmt->execute();
                                        $responders = $stmt->fetchAll();
                                        ?>
                                        <?php foreach($responders as $responder): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($responder['full_name']); ?></td>
                                            <td><?php 
                                                $stmt_org = $pdo->prepare("SELECT organization FROM responders WHERE user_id = ?");
                                                $stmt_org->execute([$responder['id']]);
                                                echo htmlspecialchars($stmt_org->fetchColumn() ?: 'N/A');
                                            ?></td>
                                            <td><?php 
                                                $stmt_type = $pdo->prepare("SELECT responder_type FROM responders WHERE user_id = ?");
                                                $stmt_type->execute([$responder['id']]);
                                                echo htmlspecialchars($stmt_type->fetchColumn() ?: 'N/A');
                                            ?></td>
                                            <td><?php echo htmlspecialchars($responder['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $responder['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $responder['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-info btn-sm" onclick="alert('View responder details')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-sm" onclick="alert('Edit responder')">
                                                        <i class="fas fa-edit"></i>
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
                    
                    <div class="modal fade" id="addResponderModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="user_management.php?action=add">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New Responder</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="role" value="responder">
                                        <div class="mb-3">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Phone</label>
                                            <input type="tel" name="phone" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Password</label>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Responder</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($action == 'analytics'): ?>
                    <?php 
                    global $pdo;
                    
                    // Get statistics
                    $stmt = $pdo->query("SELECT COUNT(*) FROM reports");
                    $total_reports = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
                    $status_stats = $stmt->fetchAll();
                    
                    $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
                    $category_stats = $stmt->fetchAll();
                    
                    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM reports GROUP BY month ORDER BY month DESC LIMIT 6");
                    $monthly_stats = $stmt->fetchAll();
                    
                    $stmt = $pdo->query("SELECT COUNT(*) FROM sos_alerts WHERE status = 'Active'");
                    $active_sos = $stmt->fetchColumn();
                    ?>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Reports by Category</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Reports by Month</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="monthChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Case Status Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Active SOS Alerts</h6>
                                </div>
                                <div class="card-body text-center">
                                    <h1 class="display-1 <?php echo $active_sos > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $active_sos; ?>
                                    </h1>
                                    <p class="lead">Active SOS Alerts</p>
                                    <?php if($active_sos > 0): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-triangle animate-pulse"></i> 
                                            <strong>URGENT:</strong> <?php echo $active_sos; ?> active SOS alerts require immediate attention!
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle"></i> All clear! No active SOS alerts.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        // Category Chart
                        new Chart(document.getElementById('categoryChart'), {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_column($category_stats, 'category')); ?>,
                                datasets: [{
                                    label: 'Number of Reports',
                                    data: <?php echo json_encode(array_column($category_stats, 'count')); ?>,
                                    backgroundColor: 'rgba(99, 102, 241, 0.6)',
                                    borderColor: 'rgba(99, 102, 241, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                        
                        // Month Chart
                        new Chart(document.getElementById('monthChart'), {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode(array_reverse(array_column($monthly_stats, 'month'))); ?>,
                                datasets: [{
                                    label: 'Reports',
                                    data: <?php echo json_encode(array_reverse(array_column($monthly_stats, 'count'))); ?>,
                                    borderColor: '#8b5cf6',
                                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                                    tension: 0.4,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                        
                        // Status Chart
                        new Chart(document.getElementById('statusChart'), {
                            type: 'pie',
                            data: {
                                labels: <?php echo json_encode(array_column($status_stats, 'status')); ?>,
                                datasets: [{
                                    data: <?php echo json_encode(array_column($status_stats, 'count')); ?>,
                                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b21a5', '#6366f1']
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>