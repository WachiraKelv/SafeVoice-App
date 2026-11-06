<?php
require_once 'config/functions.php';
requireLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if (isAdmin()) {
    if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'type' => $_POST['type'],
            'phone' => sanitizeInput($_POST['phone']),
            'address' => sanitizeInput($_POST['address']),
            'latitude' => $_POST['latitude'],
            'longitude' => $_POST['longitude'],
            'operating_hours' => sanitizeInput($_POST['operating_hours'])
        ];
        addService($data);
        redirect('support_services.php?action=list');
    }
    
    if ($action == 'delete' && isset($_GET['id'])) {
        deleteService($_GET['id']);
        redirect('support_services.php?action=list');
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
                <?php if($action == 'list'): ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Support Services Near You</h5>
                                    <?php if(isAdmin()): ?>
                                        <a href="?action=add" class="btn btn-primary btn-sm">Add Service</a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <select id="serviceFilter" class="form-select">
                                                <option value="all">All Services</option>
                                                <option value="Police Station">Police Stations</option>
                                                <option value="Hospital">Hospitals</option>
                                                <option value="Shelter">Shelters</option>
                                                <option value="Counselor">Counselors</option>
                                                <option value="Legal Aid">Legal Aid</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div id="map" class="map-container mb-4"></div>
                                    
                                    <div class="table-responsive">
                                        <table class="table" id="servicesTable">
                                            <thead>
                                                <tr><th>Name</th><th>Type</th><th>Phone</th><th>Address</th><?php if(isAdmin()): ?><th>Action</th><?php endif; ?></tr>
                                            </thead>
                                            <tbody>
                                                <?php $services = getServices(); ?>
                                                <?php foreach($services as $service): ?>
                                                <tr data-type="<?php echo $service['type']; ?>">
                                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                                    <td><?php echo $service['type']; ?></td>
                                                    <td><?php echo $service['phone']; ?></td>
                                                    <td><?php echo htmlspecialchars($service['address']); ?></td>
                                                    <?php if(isAdmin()): ?>
                                                    <td>
                                                        <a href="?action=delete&id=<?php echo $service['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete()">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        var map = L.map('map').setView([40.7128, -74.0060], 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(map);
                        
                        var markers = [];
                        var services = <?php echo json_encode($services); ?>;
                        
                        services.forEach(function(service) {
                            if (service.latitude && service.longitude) {
                                var marker = L.marker([service.latitude, service.longitude]).addTo(map);
                                marker.bindPopup(`
                                    <b>${service.name}</b><br>
                                    Type: ${service.type}<br>
                                    Phone: ${service.phone}<br>
                                    Address: ${service.address}
                                `);
                                markers.push(marker);
                            }
                        });
                        
                        document.getElementById('serviceFilter').addEventListener('change', function() {
                            var filter = this.value;
                            var rows = document.querySelectorAll('#servicesTable tbody tr');
                            
                            rows.forEach(function(row) {
                                if (filter === 'all' || row.dataset.type === filter) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        });
                    </script>
                    
                <?php elseif($action == 'add' && isAdmin()): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Add Support Service</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #dde0db;">Service Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Service Type</label>
                                        <select name="type" class="form-select" required>
                                            <option value="Police Station">Police Station</option>
                                            <option value="Hospital">Hospital</option>
                                            <option value="Shelter">Shelter</option>
                                            <option value="Counselor">Counselor</option>
                                            <option value="Legal Aid">Legal Aid</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                       <label class="form-label" style="color: #eef1ec;">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" required>
                                    </div> 
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #e2e6e0;">Operating Hours</label>
                                        <input type="text" name="operating_hours" class="form-control" placeholder="e.g., Mon-Fri 9AM-5PM">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label" style="color: #eaefe7;">Address</label>
                                        <textarea name="address" rows="2" class="form-control" required></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #f0f4ed;">Latitude</label>
                                        <input type="text" name="latitude" class="form-control" step="any">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #f7f9f5;">Longitude</label>
                                        <input type="text" name="longitude" class="form-control" step="any">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #dde0db;">Service Name</label>
                                        <input type="text" name="service_name" class="form-control" step="any">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Service</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>