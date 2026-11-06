<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'safevoice_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application paths
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/safevoice/');
define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'survivor';
$user_name = $_SESSION['full_name'] ?? 'User';

// DEBUG: Check what's in the session
// Uncomment to debug
// echo "<!-- User ID: $user_id, Role: $user_role -->";

// Functions
function getUser($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function getReports($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getReport($report_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function getAllReports() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT r.*, u.full_name as reporter_name 
                               FROM reports r 
                               LEFT JOIN users u ON r.user_id = u.id 
                               ORDER BY r.created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEvidence($report_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM evidence WHERE report_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$report_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getCaseUpdates($report_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT cu.*, u.full_name as updater_name 
                               FROM case_updates cu 
                               LEFT JOIN users u ON cu.updated_by = u.id 
                               WHERE cu.report_id = ? 
                               ORDER BY cu.created_at ASC");
        $stmt->execute([$report_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function isAdmin() {
    // Check both session role and database role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Also check database to be sure
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && $user['role'] === 'admin') {
            // Update session if needed
            $_SESSION['role'] = 'admin';
            return true;
        }
    } catch (Exception $e) {
        // Ignore
    }
    return false;
}

function isResponder() {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'responder') {
        return true;
    }
    
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && $user['role'] === 'responder') {
            $_SESSION['role'] = 'responder';
            return true;
        }
    } catch (Exception $e) {
        // Ignore
    }
    return false;
}

function isSurvivor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'survivor';
}

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle SOS POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sos') {
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sos_alerts (user_id, latitude, longitude, status, created_at) VALUES (?, ?, ?, 'Active', NOW())");
        $stmt->execute([$user_id, $lat, $lng]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle Resolve SOS
if ($action === 'resolve_sos' && isset($_GET['id']) && isAdmin()) {
    $sos_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("UPDATE sos_alerts SET status = 'Resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $sos_id]);
        header('Location: reporting.php?action=sos_admin&msg=resolved');
        exit();
    } catch (Exception $e) {
        // Handle error
    }
}

// Handle Delete Report
if ($action === 'delete' && isset($_GET['id']) && (isAdmin() || isResponder())) {
    $report_id = intval($_GET['id']);
    try {
        // Delete evidence files first
        $stmt = $pdo->prepare("SELECT file_path FROM evidence WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $evidence_files = $stmt->fetchAll();
        foreach ($evidence_files as $file) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/safevoice/' . $file['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        
        // Delete the report
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
        
        header('Location: reporting.php?action=view&msg=deleted');
        exit();
    } catch (Exception $e) {
        // Handle error
    }
}

// Handle Update Case
if ($action === 'update' && isset($_GET['id']) && (isAdmin() || isResponder())) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_text'])) {
        $status = $_POST['status'];
        $update_text = $_POST['update_text'];
        $report_id = intval($_GET['id']);
        
        try {
            $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
            $stmt->execute([$status, $report_id]);
            
            $stmt = $pdo->prepare("INSERT INTO case_updates (report_id, updated_by, update_text, status_change) VALUES (?, ?, ?, ?)");
            $stmt->execute([$report_id, $user_id, $update_text, $status]);
            
            header('Location: reporting.php?action=details&id=' . $report_id . '&msg=updated');
            exit();
        } catch (Exception $e) {
            // Handle error
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeVoice - Reporting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; }
        .navbar-brand { font-weight: bold; color: #2e7d32 !important; }
        .btn-green { background-color: #4caf50; color: white; }
        .btn-green:hover { background-color: #388e3c; color: white; }
        .table-green { border-collapse: separate; border-spacing: 0 5px; }
        .table-green thead th { background-color: #4caf50; color: white; border: none; }
        .table-green tbody tr { background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .table-green tbody tr:hover { transform: scale(1.01); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .status-resolved { background-color: #4caf50; color: white; padding: 5px 10px; border-radius: 4px; }
        .status-pending { background-color: #ff9800; color: white; padding: 5px 10px; border-radius: 4px; }
        .status-under-review { background-color: #2196f3; color: white; padding: 5px 10px; border-radius: 4px; }
        .status-assigned { background-color: #9c27b0; color: white; padding: 5px 10px; border-radius: 4px; }
        .status-investigating { background-color: #ff5722; color: white; padding: 5px 10px; border-radius: 4px; }
        .status-closed { background-color: #9e9e9e; color: white; padding: 5px 10px; border-radius: 4px; }
        .detail-label-green { font-weight: 600; color: #2e7d32; }
        .detail-value-green { color: #333; }
        .form-green .form-control:focus, .form-green .form-select:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        .evidence-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .evidence-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background: white;
        }
        .evidence-item img {
            max-width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
        }
        .evidence-item img:hover { opacity: 0.8; }
        .evidence-placeholder { padding: 30px; background: #f5f5f5; border-radius: 4px; text-align: center; }
        .evidence-placeholder i { font-size: 30px; color: #ccc; }
        .file-info { margin-top: 8px; }
        .file-info a { color: #2e7d32; text-decoration: none; }
        .file-info a:hover { text-decoration: underline; }
        .image-lightbox {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }
        .image-lightbox.show { display: flex; }
        .image-lightbox img { max-width: 90%; max-height: 90%; object-fit: contain; }
        .close-lightbox {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-lightbox:hover { color: #ccc; }
        .card-hover { transition: transform 0.3s; }
        .card-hover:hover { transform: translateY(-5px); }
        .badge { font-size: 0.85rem; padding: 5px 12px; }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .icon {
            font-size: 40px;
            opacity: 0.7;
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stats-card .label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="reporting.php">
                <i class="fas fa-shield-alt text-success"></i> SafeVoice
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user_name); ?></span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link badge bg-success"><?php echo ucfirst($user_role); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reporting.php?action=create">
                            <i class="fas fa-plus-circle"></i> New Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reporting.php?action=view">
                            <i class="fas fa-list"></i> My Reports
                        </a>
                    </li>
                    <?php if(isSurvivor()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="reporting.php?action=sos">
                            <i class="fas fa-exclamation-triangle"></i> SOS
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if(isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="reporting.php?action=sos_admin">
                            <i class="fas fa-exclamation-triangle text-danger"></i> SOS Alerts
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <?php if($action == 'admin_dashboard' && isAdmin()): ?>
                    <!-- Admin Dashboard -->
                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="mb-3"><i class="fas fa-tachometer-alt text-success"></i> Dashboard Overview</h4>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <?php
                        // Get statistics
                        $total_reports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
                        $active_sos = $pdo->query("SELECT COUNT(*) FROM sos_alerts WHERE status = 'Active'")->fetchColumn();
                        $pending_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'")->fetchColumn();
                        $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="label">Total Reports</div>
                                        <div class="number"><?php echo $total_reports; ?></div>
                                    </div>
                                    <div class="icon text-primary"><i class="fas fa-file-alt"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="label">Pending Reports</div>
                                        <div class="number"><?php echo $pending_reports; ?></div>
                                    </div>
                                    <div class="icon text-warning"><i class="fas fa-clock"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="label">Active SOS Alerts</div>
                                        <div class="number"><?php echo $active_sos; ?></div>
                                    </div>
                                    <div class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="label">Total Users</div>
                                        <div class="number"><?php echo $total_users; ?></div>
                                    </div>
                                    <div class="icon text-success"><i class="fas fa-users"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- All Reports Table for Admin -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> All Reports</h5>
                            <div>
                                <a href="?action=create" class="btn btn-green btn-sm me-2">
                                    <i class="fas fa-plus"></i> New Report
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-green">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Reporter</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $all_reports = getAllReports(); ?>
                                        <?php if(count($all_reports) > 0): ?>
                                            <?php foreach($all_reports as $report): ?>
                                            <tr>
                                                <td>#<?php echo $report['id']; ?></td>
                                                <td><?php echo htmlspecialchars($report['title']); ?></td>
                                                <td><?php echo htmlspecialchars($report['reporter_name'] ?: 'Anonymous'); ?></td>
                                                <td><?php echo $report['category']; ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $report['status'] == 'Resolved' ? 'status-resolved' : 
                                                            ($report['status'] == 'Pending' ? 'status-pending' : 
                                                            ($report['status'] == 'Under Review' ? 'status-under-review' : 
                                                            ($report['status'] == 'Assigned' ? 'status-assigned' : 
                                                            ($report['status'] == 'Investigating' ? 'status-investigating' : 'status-closed')))); 
                                                    ?>">
                                                        <?php echo $report['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=details&id=<?php echo $report['id']; ?>" class="btn btn-green">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="?action=delete&id=<?php echo $report['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this report?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle"></i> No reports found in the system.
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($action == 'sos_admin' && isAdmin()): ?>
                    <!-- SOS Admin View -->
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> SOS Alerts Management</h5>
                        </div>
                        <div class="card-body">
                            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'resolved'): ?>
                                <div class="alert alert-success">SOS alert has been resolved successfully!</div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-green">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Survivor</th>
                                            <th>Date & Time</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT s.*, u.full_name as survivor_name FROM sos_alerts s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'Active' ORDER BY s.created_at DESC");
                                            $stmt->execute();
                                            $sos_alerts = $stmt->fetchAll();
                                            
                                            if(count($sos_alerts) > 0):
                                                foreach($sos_alerts as $alert):
                                        ?>
                                            <tr>
                                                <td>#<?php echo $alert['id']; ?></td>
                                                <td><?php echo htmlspecialchars($alert['survivor_name']); ?></td>
                                                <td><?php echo date('M d, Y H:i:s', strtotime($alert['created_at'])); ?></td>
                                                <td>
                                                    <?php if($alert['latitude'] && $alert['longitude']): ?>
                                                        <button class="btn btn-sm btn-info" onclick="showSOSLocation(<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>, '<?php echo htmlspecialchars($alert['survivor_name']); ?>')">
                                                            <i class="fas fa-map-marker-alt"></i> View Location
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Location not captured</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo $alert['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="resolveSOS(<?php echo $alert['id']; ?>, '<?php echo htmlspecialchars($alert['survivor_name']); ?>')">
                                                        <i class="fas fa-check"></i> Resolve
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php 
                                                endforeach;
                                            else:
                                        ?>
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <div class="alert alert-success mb-0">
                                                        <i class="fas fa-check-circle"></i> No active SOS alerts. Everyone is safe!
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                            endif;
                                        } catch (Exception $e) {
                                            echo '<tr><td colspan="6" class="text-center"><div class="alert alert-danger">Error loading SOS alerts: ' . $e->getMessage() . '</div></td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal fade" id="sosLocationModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="fas fa-map-marker-alt me-2"></i> SOS Alert Location</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="sosMap" style="height: 400px;"></div>
                                    <div id="sosLocationInfo" class="mt-2"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-success" onclick="openGoogleMaps()">
                                        <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
                    <script>
                        let currentLat = null;
                        let currentLng = null;
                        let map = null;
                        
                        function showSOSLocation(lat, lng, name) {
                            currentLat = lat;
                            currentLng = lng;
                            
                            document.getElementById('sosLocationInfo').innerHTML = 
                                '<div class="alert alert-info"><i class="fas fa-user"></i> <strong>' + name + '</strong> - SOS Alert Location</div>';
                            
                            if (!map) {
                                map = L.map('sosMap').setView([lat, lng], 15);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '© OpenStreetMap contributors'
                                }).addTo(map);
                            } else {
                                map.setView([lat, lng], 15);
                            }
                            
                            L.marker([lat, lng]).addTo(map)
                                .bindPopup('<b>SOS Alert</b><br>' + name)
                                .openPopup();
                            
                            L.circle([lat, lng], {
                                color: 'red',
                                fillColor: '#f03',
                                fillOpacity: 0.2,
                                radius: 100
                            }).addTo(map);
                            
                            setTimeout(function() {
                                map.invalidateSize();
                            }, 200);
                            
                            var modal = new bootstrap.Modal(document.getElementById('sosLocationModal'));
                            modal.show();
                        }
                        
                        function openGoogleMaps() {
                            if (currentLat && currentLng) {
                                window.open(`https://www.google.com/maps?q=${currentLat},${currentLng}`, '_blank');
                            }
                        }
                        
                        function resolveSOS(sosId, survivorName) {
                            if (confirm('⚠️ CONFIRM RESOLVE SOS ALERT\n\nSurvivor: ' + survivorName + '\n\nOnly mark as resolved when:\n✓ The survivor is safe\n✓ Emergency services have arrived\n✓ The situation is under control')) {
                                window.location.href = 'reporting.php?action=resolve_sos&id=' + sosId;
                            }
                        }
                    </script>
                    
                <?php elseif($action == 'sos' && isSurvivor()): ?>
                    <!-- SOS Survivor View -->
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Emergency SOS Alert</h5>
                        </div>
                        <div class="card-body text-center py-5">
                            <div class="alert alert-warning">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>Your Safety is Our Priority</strong> - Only use this in real emergencies.
                            </div>
                            <p class="lead">If you are in immediate danger, click the SOS button below to send an emergency alert.</p>
                            <button class="btn btn-danger btn-lg" id="sosButton" style="padding: 15px 40px; font-size: 1.5rem;">
                                <i class="fas fa-exclamation-triangle me-2"></i> SEND SOS ALERT
                            </button>
                            <p class="text-muted mt-3">
                                <i class="fas fa-info-circle"></i> Your location will be shared with emergency responders.
                            </p>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> My SOS Alert History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-green">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date & Time</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Resolved By</th>
                                            <th>Resolved At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT s.*, u.full_name as resolver_name FROM sos_alerts s LEFT JOIN users u ON s.resolved_by = u.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
                                            $stmt->execute([$user_id]);
                                            $sos_alerts = $stmt->fetchAll();
                                            
                                            if(count($sos_alerts) > 0):
                                                foreach($sos_alerts as $alert):
                                        ?>
                                            <tr>
                                                <td>#<?php echo $alert['id']; ?></td>
                                                <td><?php echo date('M d, Y H:i:s', strtotime($alert['created_at'])); ?></td>
                                                <td>
                                                    <?php if($alert['latitude'] && $alert['longitude']): ?>
                                                        <button class="btn btn-sm btn-info" onclick="showSOSLocation(<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>, 'You')">
                                                            <i class="fas fa-map-marker-alt"></i> View Location
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Location not captured</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $alert['status'] == 'Active' ? 'danger' : 'success'; ?>">
                                                        <?php echo $alert['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $alert['resolver_name'] ?: 'Not yet resolved'; ?></td>
                                                <td><?php echo $alert['resolved_at'] ? date('M d, Y H:i', strtotime($alert['resolved_at'])) : '-'; ?></td>
                                            </tr>
                                        <?php 
                                                endforeach;
                                            else:
                                        ?>
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle"></i> No SOS alerts triggered yet. You're safe!
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                            endif;
                                        } catch (Exception $e) {
                                            echo '<tr><td colspan="6" class="text-center"><div class="alert alert-danger">Error loading SOS history: ' . $e->getMessage() . '</div></td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const sosButton = document.getElementById('sosButton');
                            if (sosButton) {
                                sosButton.addEventListener('click', function() {
                                    if (!navigator.geolocation) {
                                        alert('Geolocation is not supported by your browser');
                                        return;
                                    }
                                    
                                    if (!confirm('⚠️ WARNING: This will send an emergency alert to responders. Only use in real emergencies!')) {
                                        return;
                                    }
                                    
                                    navigator.geolocation.getCurrentPosition(function(position) {
                                        const lat = position.coords.latitude;
                                        const lng = position.coords.longitude;
                                        
                                        fetch('reporting.php?action=sos', {
                                            method: 'POST',
                                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                            body: 'lat=' + lat + '&lng=' + lng
                                        }).then(response => response.json()).then(data => {
                                            if (data.success) {
                                                alert('✅ SOS Alert Sent Successfully!\n\nHelp has been notified and is on the way.\n\nStay safe!');
                                                location.reload();
                                            } else {
                                                alert('❌ Failed to send SOS alert. Please try again or call emergency services.');
                                            }
                                        }).catch(error => {
                                            alert('Error sending SOS. Please call emergency services.\nPolice: 911');
                                        });
                                    }, function(error) {
                                        alert('Unable to get location. Please enable location services.');
                                    });
                                });
                            }
                        });
                    </script>
                    
                <?php elseif($action == 'create'): ?>
                    <!-- Create Report View -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Report an Incident</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="reportForm" class="form-green">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-heading me-1"></i> Title</label>
                                        <input type="text" name="title" class="form-control" required placeholder="Brief title of the incident">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-tag me-1"></i> Category</label>
                                        <select name="category" class="form-select" required>
                                            <option value="Physical Violence">Physical Violence</option>
                                            <option value="Sexual Violence">Sexual Violence</option>
                                            <option value="Domestic Violence">Domestic Violence</option>
                                            <option value="Emotional Abuse">Emotional Abuse</option>
                                            <option value="Child Abuse">Child Abuse</option>
                                            <option value="Cyber Harassment">Cyber Harassment</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-calendar me-1"></i> Incident Date</label>
                                        <input type="date" name="incident_date" class="form-control" required>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-map-pin me-1"></i> Location</label>
                                        <input type="text" name="location" class="form-control" placeholder="City, Area, or specific location">
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-align-left me-1"></i> Description</label>
                                        <textarea name="description" rows="5" class="form-control" required placeholder="Describe what happened in detail..."></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-paperclip me-1"></i> Upload Evidence (Optional)</label>
                                        <input type="file" name="evidence" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.mp4,.docx">
                                        <small class="text-muted">Supported: JPG, PNG, GIF, PDF, MP4, DOCX (Max 10MB)</small>
                                    </div>
                                    <div class="col-md-12 mb-3 form-check">
                                        <input type="checkbox" name="is_anonymous" class="form-check-input" id="anonymous">
                                        <label class="form-check-label" for="anonymous">Submit Anonymously</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-green">Submit Report</button>
                            </form>
                        </div>
                    </div>
                    
                    <?php
                    // Handle form submission
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
                        $title = $_POST['title'];
                        $category = $_POST['category'];
                        $incident_date = $_POST['incident_date'];
                        $location = $_POST['location'] ?? '';
                        $description = $_POST['description'];
                        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO reports (user_id, title, category, description, incident_date, location, is_anonymous, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                            $stmt->execute([$user_id, $title, $category, $description, $incident_date, $location, $is_anonymous]);
                            $report_id = $pdo->lastInsertId();
                            
                            // Handle file upload
                            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                                $file_tmp = $_FILES['evidence']['tmp_name'];
                                $file_name = time() . '_' . $_FILES['evidence']['name'];
                                $file_path = 'assets/uploads/' . $file_name;
                                $full_path = UPLOAD_PATH . $file_name;
                                
                                if (move_uploaded_file($file_tmp, $full_path)) {
                                    $stmt = $pdo->prepare("INSERT INTO evidence (report_id, file_name, file_path) VALUES (?, ?, ?)");
                                    $stmt->execute([$report_id, $_FILES['evidence']['name'], $file_path]);
                                }
                            }
                            
                            echo '<div class="alert alert-success mt-3">✅ Report submitted successfully! <a href="?action=view">View all reports</a></div>';
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger mt-3">Error: ' . $e->getMessage() . '</div>';
                        }
                    }
                    ?>
                    
                <?php elseif($action == 'view'): ?>
                    <!-- View Reports -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> <?php echo (isAdmin() || isResponder()) ? 'All Reports' : 'My Reports'; ?></h5>
                            <a href="?action=create" class="btn btn-green btn-sm">
                                <i class="fas fa-plus"></i> New Report
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                                <div class="alert alert-success">Report deleted successfully!</div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-green">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // If admin or responder, show all reports
                                        if(isAdmin() || isResponder()) {
                                            $reports = getAllReports();
                                        } else {
                                            $reports = getReports($user_id);
                                        }
                                        ?>
                                        <?php if(count($reports) > 0): ?>
                                            <?php foreach($reports as $report): ?>
                                            <tr>
                                                <td>#<?php echo $report['id']; ?></td>
                                                <td><?php echo htmlspecialchars($report['title']); ?></td>
                                                <td><?php echo $report['category']; ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $report['status'] == 'Resolved' ? 'status-resolved' : 
                                                            ($report['status'] == 'Pending' ? 'status-pending' : 
                                                            ($report['status'] == 'Under Review' ? 'status-under-review' : 
                                                            ($report['status'] == 'Assigned' ? 'status-assigned' : 
                                                            ($report['status'] == 'Investigating' ? 'status-investigating' : 'status-closed')))); 
                                                    ?>">
                                                        <?php echo $report['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars(substr($report['location'], 0, 30)) ?: 'Not specified'; ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=details&id=<?php echo $report['id']; ?>" class="btn btn-green">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <?php if(isAdmin() || isResponder()): ?>
                                                            <a href="?action=delete&id=<?php echo $report['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this report?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle"></i> No reports found. 
                                                        <a href="?action=create">Create your first report</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($action == 'details' && isset($_GET['id'])): ?>
                    <!-- Report Details -->
                    <?php 
                    $report_id = intval($_GET['id']);
                    $report = getReport($report_id); 
                    $isAdminOrResponder = isAdmin() || isResponder();
                    
                    // Debug output
                    // echo "<!-- isAdmin: " . (isAdmin() ? 'true' : 'false') . ", isResponder: " . (isResponder() ? 'true' : 'false') . " -->";
                    ?>
                    <?php if($report): ?>
                        <?php if($report['user_id'] == $user_id || $isAdminOrResponder): ?>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card mb-4">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> Report #<?php echo $report['id']; ?>: <?php echo htmlspecialchars($report['title']); ?></h5>
                                            <div>
                                                <?php if($isAdminOrResponder): ?>
                                                    <a href="?action=delete&id=<?php echo $report['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this report?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <span class="detail-label-green">Category:</span>
                                                    <span class="detail-value-green"><?php echo $report['category']; ?></span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="detail-label-green">Status:</span>
                                                    <span class="badge <?php 
                                                        echo $report['status'] == 'Resolved' ? 'status-resolved' : 
                                                            ($report['status'] == 'Pending' ? 'status-pending' : 
                                                            ($report['status'] == 'Under Review' ? 'status-under-review' : 
                                                            ($report['status'] == 'Assigned' ? 'status-assigned' : 
                                                            ($report['status'] == 'Investigating' ? 'status-investigating' : 'status-closed')))); 
                                                    ?>">
                                                        <?php echo $report['status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <span class="detail-label-green">Incident Date:</span>
                                                    <span class="detail-value-green"><?php echo date('F d, Y', strtotime($report['incident_date'])); ?></span>
                                                </div>
                                                <div class="col-md-6">
                                                    <span class="detail-label-green">Reported:</span>
                                                    <span class="detail-value-green"><?php echo date('F d, Y', strtotime($report['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <span class="detail-label-green">Location:</span>
                                                <span class="detail-value-green"><?php echo htmlspecialchars($report['location']) ?: 'Not specified'; ?></span>
                                            </div>
                                            <div class="mb-3">
                                                <span class="detail-label-green">Description:</span>
                                                <p class="detail-value-green mt-2 p-3" style="background: #f1f8e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                                                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                                </p>
                                            </div>
                                            
                                            <?php 
                                            $evidence = getEvidence($report['id']); 
                                            ?>
                                            <?php if(count($evidence) > 0): ?>
                                                <div class="mt-4">
                                                    <h6 class="detail-label-green"><i class="fas fa-paperclip me-2"></i> Evidence (<?php echo count($evidence); ?> files)</h6>
                                                    <div class="evidence-gallery">
                                                        <?php foreach($evidence as $item): 
                                                            $file_path_from_db = $item['file_path'];
                                                            $file_name = basename(str_replace('\\', '/', $file_path_from_db));
                                                            $file_url = 'assets/uploads/' . $file_name;
                                                            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/safevoice/assets/uploads/' . $file_name;
                                                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                            $file_exists = file_exists($full_path);
                                                        ?>
                                                            <div class="evidence-item">
                                                                <?php if($is_image && $file_exists): ?>
                                                                    <img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" 
                                                                         alt="<?php echo htmlspecialchars($file_name); ?>" 
                                                                         loading="lazy"
                                                                         onclick="openLightbox('<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>')"
                                                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'evidence-placeholder\'><i class=\'fas fa-image\'></i><span>Image not found</span></div>'">
                                                                <?php else: ?>
                                                                    <div class="evidence-placeholder">
                                                                        <i class="fas fa-file"></i>
                                                                        <a href="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-sm btn-green">Download</a>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="file-info">
                                                                    <small>
                                                                        <a href="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                                                            <?php echo htmlspecialchars($file_name); ?>
                                                                        </a>
                                                                        <br>
                                                                        <span class="text-muted">Uploaded: <?php echo date('M d, Y', strtotime($item['uploaded_at'])); ?></span>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info mt-3">
                                                    <i class="fas fa-info-circle"></i> No evidence uploaded for this report.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php $updates = getCaseUpdates($report['id']); ?>
                                    <?php if(count($updates) > 0): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-timeline me-2"></i> Case Timeline</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php foreach($updates as $update): ?>
                                                <div class="mb-3 pb-3 border-bottom">
                                                    <strong><?php echo htmlspecialchars($update['updater_name']); ?></strong> 
                                                    <small class="text-muted">- <?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?></small>
                                                    <p class="mb-0 mt-2 detail-value-green"><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                                    <?php if($update['status_change']): ?>
                                                        <span class="badge bg-info mt-2">Status changed to: <?php echo $update['status_change']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4">
                                    <?php if($isAdminOrResponder): ?>
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-edit me-2"></i> Update Case</h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="?action=update&id=<?php echo $report['id']; ?>" class="form-green">
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="Pending" <?php echo $report['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="Under Review" <?php echo $report['status'] == 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                                            <option value="Assigned" <?php echo $report['status'] == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                                            <option value="Investigating" <?php echo $report['status'] == 'Investigating' ? 'selected' : ''; ?>>Investigating</option>
                                                            <option value="Resolved" <?php echo $report['status'] == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                            <option value="Closed" <?php echo $report['status'] == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Update Notes</label>
                                                        <textarea name="update_text" rows="3" class="form-control" required placeholder="Add case update notes..."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-green w-100">Update Case</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($report['assigned_responder_id']): ?>
                                        <?php $responder = getUser($report['assigned_responder_id']); ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-user-shield me-2"></i> Assigned Responder</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($responder['full_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($responder['email']); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($responder['phone']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> You don't have permission to view this report.
                                <br><small>This report belongs to another user. Only admins and responders can view all reports.</small>
                                <br><small>Debug: User ID: <?php echo $user_id; ?>, Report Owner: <?php echo $report['user_id']; ?>, isAdmin: <?php echo isAdmin() ? 'Yes' : 'No'; ?>, isResponder: <?php echo isResponder() ? 'Yes' : 'No'; ?></small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Report not found.
                            <br><small>Report ID <?php echo $report_id; ?> does not exist in the database.</small>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Default Home/Dashboard View -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-home me-2"></i> Welcome to SafeVoice Reporting System</h5>
                                </div>
                                <div class="card-body text-center py-5">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="card card-hover h-100">
                                                <div class="card-body">
                                                    <i class="fas fa-plus-circle" style="font-size: 48px; color: #4caf50;"></i>
                                                    <h5 class="mt-3">Report an Incident</h5>
                                                    <p class="text-muted">Submit a new report about an incident</p>
                                                    <a href="?action=create" class="btn btn-green">Create Report</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card card-hover h-100">
                                                <div class="card-body">
                                                    <i class="fas fa-list" style="font-size: 48px; color: #2196f3;"></i>
                                                    <h5 class="mt-3">View My Reports</h5>
                                                    <p class="text-muted">See all your submitted reports</p>
                                                    <a href="?action=view" class="btn btn-primary">View Reports</a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if(isSurvivor()): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card card-hover h-100 border-danger">
                                                <div class="card-body">
                                                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #dc3545;"></i>
                                                    <h5 class="mt-3">SOS Emergency</h5>
                                                    <p class="text-muted">Send emergency alert in danger</p>
                                                    <a href="?action=sos" class="btn btn-danger">SOS Alert</a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if(isAdmin()): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card card-hover h-100 border-primary">
                                                <div class="card-body">
                                                    <i class="fas fa-tachometer-alt" style="font-size: 48px; color: #2196f3;"></i>
                                                    <h5 class="mt-3">Admin Dashboard</h5>
                                                    <p class="text-muted">Manage reports and SOS alerts</p>
                                                    <a href="?action=admin_dashboard" class="btn btn-primary">Dashboard</a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div class="image-lightbox" id="imageLightbox" onclick="closeLightbox()">
        <span class="close-lightbox">&times;</span>
        <img id="lightboxImage" src="" alt="Evidence Image">
    </div>

    <script>
        function openLightbox(imageSrc) {
            document.getElementById('lightboxImage').src = imageSrc;
            document.getElementById('imageLightbox').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('imageLightbox').classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>