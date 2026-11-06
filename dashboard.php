<?php
require_once 'config/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
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
                <?php if(isSurvivor()): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>My Reports</h6>
                                <h3><?php echo count(getReports($user_id)); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Pending Cases</h6>
                                <h3><?php echo count(getReports($user_id, 'Pending')); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Resolved Cases</h6>
                                <h3><?php echo count(getReports($user_id, 'Resolved')); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>SOS Alerts</h6>
                                <h3><?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sos_alerts WHERE user_id = ? AND status = 'Active'");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetchColumn();
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Reports</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Title</th><th>Category</th><th>Status</th><th>Date</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $reports = getReports($user_id);
                                        $recent_reports = array_slice($reports, 0, 5);
                                        foreach($recent_reports as $report): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['title']); ?></td>
                                            <td><?php echo $report['category']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $report['status'] == 'Resolved' ? 'success' : 
                                                        ($report['status'] == 'Pending' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo $report['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button class="sos-button" data-bs-toggle="modal" data-bs-target="#sosModal">
                            <i class="fas fa-exclamation-triangle me-2"></i> SEND SOS ALERT
                        </button>
                    </div>
                    
                <?php elseif(isAdmin()): ?>
                    <?php $stats = getStatistics(); ?>
                    <div class="row mb-4">
                        <div class="col-md-3"><div class="stat-card"><h6>Total Users</h6><h3><?php echo $stats['total_survivors']; ?></h3></div></div>
                        <div class="col-md-3"><div class="stat-card"><h6>Total Reports</h6><h3><?php echo $stats['total_reports']; ?></h3></div></div>
                        <div class="col-md-3"><div class="stat-card"><h6>Active SOS</h6><h3><?php echo $stats['active_sos']; ?></h3></div></div>
                        <div class="col-md-3"><div class="stat-card"><h6>Resolved Cases</h6><h3><?php echo $stats['resolved_cases']; ?></h3></div></div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Active SOS Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Survivor</th>
                                            <th>Phone</th>
                                            <th>Location</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT s.*, u.full_name, u.phone FROM sos_alerts s JOIN users u ON s.user_id = u.id WHERE s.status = 'Active' ORDER BY s.created_at DESC");
                                        $stmt->execute();
                                        $active_sos = $stmt->fetchAll();
                                        ?>
                                        <?php if(count($active_sos) > 0): ?>
                                            <?php foreach($active_sos as $sos): ?>
                                            <tr class="table-danger">
                                                <td>#<?php echo $sos['id']; ?></td>
                                                <td><?php echo htmlspecialchars($sos['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sos['phone']); ?></td>
                                                <td>
                                                    <?php if($sos['latitude'] && $sos['longitude']): ?>
                                                        <button class="btn btn-sm btn-info" onclick="showSOSMap(<?php echo $sos['latitude']; ?>, <?php echo $sos['longitude']; ?>, '<?php echo htmlspecialchars($sos['full_name']); ?>')">
                                                            <i class="fas fa-map-marker-alt"></i> View Map
                                                        </button>
                                                        <br>
                                                        <small>Lat: <?php echo $sos['latitude']; ?>, Lng: <?php echo $sos['longitude']; ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No coordinates</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y H:i:s', strtotime($sos['created_at'])); ?></td>
                                                <td><span class="badge bg-danger animate-pulse">ACTIVE</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="resolveSOS(<?php echo $sos['id']; ?>)">
                                                        <i class="fas fa-check"></i> Resolve
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="7" class="text-center">No active SOS alerts</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">Reports by Category</div>
                                <div class="card-body">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">Case Status Distribution</div>
                                <div class="card-body">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                        new Chart(categoryCtx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_column($stats['reports_by_category'], 'category')); ?>,
                                datasets: [{
                                    label: 'Number of Reports',
                                    data: <?php echo json_encode(array_column($stats['reports_by_category'], 'count')); ?>,
                                    backgroundColor: 'rgba(102, 126, 234, 0.5)'
                                }]
                            }
                        });
                        
                        const statusCtx = document.getElementById('statusChart').getContext('2d');
                        new Chart(statusCtx, {
                            type: 'pie',
                            data: {
                                labels: <?php echo json_encode(array_column($stats['case_status'], 'status')); ?>,
                                datasets: [{
                                    data: <?php echo json_encode(array_column($stats['case_status'], 'count')); ?>,
                                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#6B21A5']
                                }]
                            }
                        });
                        
                        function showSOSMap(lat, lng, name) {
                            var map = L.map('adminSOSMap').setView([lat, lng], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap contributors'
                            }).addTo(map);
                            L.marker([lat, lng]).addTo(map)
                                .bindPopup('<b>SOS Alert from ' + name + '</b>').openPopup();
                            
                            $('#adminSOSModal').modal('show');
                            setTimeout(function() { map.invalidateSize(); }, 100);
                        }
                        
                        function resolveSOS(sosId) {
                            if (confirm('Mark this SOS alert as resolved?')) {
                                window.location.href = 'reporting.php?action=resolve_sos&id=' + sosId;
                            }
                        }
                    </script>
                    
                <?php elseif(isResponder()): ?>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h6>Assigned Cases</h6>
                                <h3><?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE assigned_responder_id = ?");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetchColumn();
                                ?></h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h6>Pending Cases</h6>
                                <h3><?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE assigned_responder_id = ? AND status NOT IN ('Resolved', 'Closed')");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetchColumn();
                                ?></h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h6>Completed Cases</h6>
                                <h3><?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE assigned_responder_id = ? AND status = 'Resolved'");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetchColumn();
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 animate-pulse"></i> Active SOS Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Survivor Name</th>
                                            <th>Phone</th>
                                            <th>Location</th>
                                            <th>Alert Time</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT s.*, u.full_name, u.phone FROM sos_alerts s JOIN users u ON s.user_id = u.id WHERE s.status = 'Active' ORDER BY s.created_at DESC");
                                        $stmt->execute();
                                        $active_sos = $stmt->fetchAll();
                                        ?>
                                        <?php if(count($active_sos) > 0): ?>
                                            <?php foreach($active_sos as $sos): ?>
                                            <tr class="table-danger">
                                                <td><span class="badge bg-danger">#<?php echo $sos['id']; ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($sos['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($sos['phone']); ?></td>
                                                <td>
                                                    <?php if($sos['latitude'] && $sos['longitude']): ?>
                                                        <button class="btn btn-sm btn-info mb-1" onclick="showResponderSOSMap(<?php echo $sos['latitude']; ?>, <?php echo $sos['longitude']; ?>, '<?php echo htmlspecialchars($sos['full_name']); ?>')">
                                                            <i class="fas fa-map-marker-alt"></i> View on Map
                                                        </button>
                                                        <br>
                                                        <small class="text-muted">Lat: <?php echo $sos['latitude']; ?>, Lng: <?php echo $sos['longitude']; ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No coordinates available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y H:i:s', strtotime($sos['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-danger animate-pulse" style="font-size: 0.9rem;">
                                                        <i class="fas fa-circle"></i> ACTIVE
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="resolveSOSAlert(<?php echo $sos['id']; ?>, '<?php echo htmlspecialchars($sos['full_name']); ?>')">
                                                        <i class="fas fa-check-circle"></i> Mark Resolved
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">
                                                    <div class="alert alert-success mb-0">
                                                        <i class="fas fa-check-circle"></i> No active SOS alerts. Good job!
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> My Assigned Cases</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Report ID</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Reported On</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM reports WHERE assigned_responder_id = ? ORDER BY created_at DESC LIMIT 10");
                                        $stmt->execute([$user_id]);
                                        $assigned_cases = $stmt->fetchAll();
                                        ?>
                                        <?php if(count($assigned_cases) > 0): ?>
                                            <?php foreach($assigned_cases as $case): ?>
                                            <tr>
                                                <td>#<?php echo $case['id']; ?></td>
                                                <td><?php echo htmlspecialchars($case['title']); ?></td>
                                                <td><?php echo $case['category']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $case['status'] == 'Resolved' ? 'success' : 
                                                            ($case['status'] == 'Pending' ? 'warning' : 'info'); 
                                                    ?>">
                                                        <?php echo $case['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                                <td>
                                                    <a href="reporting.php?action=details&id=<?php echo $case['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center">No assigned cases yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sosModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Emergency SOS Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you in danger? Click the button below to send an immediate SOS alert. Your location will be shared with our emergency response team and your trusted contacts.</p>
                <div id="sosLocationStatus" class="alert alert-info">
                    <i class="fas fa-spinner fa-spin me-2"></i>Getting your location...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmSOS">SEND SOS NOW</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sosMapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-map-marker-alt me-2"></i> SOS Alert Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="sosLocationMap" style="height: 400px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let userLocation = null;
let responderMap = null;

document.getElementById('sosModal')?.addEventListener('show.bs.modal', function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            document.getElementById('sosLocationStatus').innerHTML = 
                '<i class="fas fa-check-circle me-2"></i>Location captured successfully at: ' + 
                userLocation.lat.toFixed(6) + ', ' + userLocation.lng.toFixed(6);
            document.getElementById('sosLocationStatus').className = 'alert alert-success';
        }, function(error) {
            let errorMsg = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = 'Location permission denied. Please enable location services.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMsg = 'Location request timed out.';
                    break;
                default:
                    errorMsg = 'Unable to get location.';
            }
            document.getElementById('sosLocationStatus').innerHTML = 
                '<i class="fas fa-exclamation-circle me-2"></i>' + errorMsg;
            document.getElementById('sosLocationStatus').className = 'alert alert-warning';
        });
    } else {
        document.getElementById('sosLocationStatus').innerHTML = 
            'Geolocation is not supported by your browser';
    }
});

document.getElementById('confirmSOS')?.addEventListener('click', function() {
    let lat = userLocation ? userLocation.lat : 0;
    let lng = userLocation ? userLocation.lng : 0;
    
    if (lat === 0 && lng === 0) {
        if (!confirm('Location not available. Send SOS without location?')) {
            return;
        }
    }
    
    fetch('reporting.php?action=sos', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'lat=' + lat + '&lng=' + lng
    }).then(response => response.json()).then(data => {
        if (data.success) {
            alert('✅ SOS Alert Sent Successfully!\n\nEmergency responders have been notified.\nYour trusted contacts have also been alerted.\n\nHelp is on the way!');
            location.reload();
        } else {
            alert('❌ Failed to send SOS alert.\n\nPlease call emergency services immediately:\nPolice: 911\nMedical: 911');
        }
    }).catch(error => {
        alert('Error sending SOS. Please call emergency services immediately.\nPolice: 911\nMedical: 911');
    });
});

function showResponderSOSMap(lat, lng, name) {
    if (responderMap) {
        responderMap.remove();
    }
    
    responderMap = L.map('sosLocationMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(responderMap);
    
    L.marker([lat, lng]).addTo(responderMap)
        .bindPopup('<b>SOS Alert from ' + name + '</b><br>Time: ' + new Date().toLocaleString())
        .openPopup();
    
    setTimeout(function() {
        responderMap.invalidateSize();
    }, 100);
    
    var modal = new bootstrap.Modal(document.getElementById('sosMapModal'));
    modal.show();
}

function resolveSOSAlert(sosId, survivorName) {
    if (confirm('⚠️ Confirm RESOLVE SOS Alert\n\nSurvivor: ' + survivorName + '\n\nOnly mark as resolved when the survivor is safe!\n\nThis will notify the survivor that help has arrived.')) {
        window.location.href = 'reporting.php?action=resolve_sos&id=' + sosId;
    }
}

function showSOSMap(lat, lng, name) {
    showResponderSOSMap(lat, lng, name);
}

function resolveSOS(sosId) {
    resolveSOSAlert(sosId, 'survivor');
}
</script>

<?php include 'includes/footer.php'; ?>