<?php
require_once 'config/functions.php';

global $pdo;
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$userCount = $stmt->fetchColumn();

if ($userCount > 0) {
    die("System already has users. Installation is locked for security.");
}

$sampleUsers = [
    [
        'full_name' => 'Admin User',
        'email' => 'admin@safevoice.com',
        'phone' => '1234567890',
        'gender' => 'Other',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'is_active' => 1
    ],
    [
        'full_name' => 'Sarah Johnson',
        'email' => 'sarah@example.com',
        'phone' => '9876543210',
        'gender' => 'Female',
        'password' => password_hash('sarah123', PASSWORD_DEFAULT),
        'role' => 'survivor',
        'is_active' => 1
    ],
    [
        'full_name' => 'John Smith',
        'email' => 'john@example.com',
        'phone' => '5551234567',
        'gender' => 'Male',
        'password' => password_hash('john123', PASSWORD_DEFAULT),
        'role' => 'survivor',
        'is_active' => 1
    ],
    [
        'full_name' => 'Dr. Emily Brown',
        'email' => 'responder@safevoice.com',
        'phone' => '5559876543',
        'gender' => 'Female',
        'password' => password_hash('responder123', PASSWORD_DEFAULT),
        'role' => 'responder',
        'is_active' => 1
    ]
];

$insertStmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, gender, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($sampleUsers as $user) {
    $insertStmt->execute([
        $user['full_name'],
        $user['email'],
        $user['phone'],
        $user['gender'],
        $user['password'],
        $user['role'],
        $user['is_active']
    ]);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'sarah@example.com'");
$stmt->execute();
$sarah = $stmt->fetch();

if ($sarah) {
    $contactStmt = $pdo->prepare("INSERT INTO trusted_contacts (user_id, name, relationship, phone) VALUES (?, ?, ?, ?)");
    $contactStmt->execute([$sarah['id'], 'Mike Johnson', 'Brother', '5551112222']);
    $contactStmt->execute([$sarah['id'], 'Lisa Johnson', 'Mother', '5553334444']);
}

$sampleServices = [
    ['Central Police Station', 'Police Station', '911', '123 Main St, City Center', 40.7128, -74.0060, 1],
    ['City General Hospital', 'Hospital', '555-0123', '456 Health Ave, City', 40.7140, -74.0070, 1],
    ['Hope Shelter', 'Shelter', '555-0456', '789 Safe St, City', 40.7150, -74.0080, 1],
    ['Wellness Counseling Center', 'Counselor', '555-0789', '321 Peace Blvd, City', 40.7160, -74.0090, 1],
    ['Legal Aid Society', 'Legal Aid', '555-1011', '654 Justice Way, City', 40.7170, -74.0100, 1]
];

$serviceStmt = $pdo->prepare("INSERT INTO services (name, type, phone, address, latitude, longitude, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($sampleServices as $service) {
    $serviceStmt->execute($service);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SafeVoice Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Installation Complete!</h4>
                    </div>
                    <div class="card-body">
                        <h5>Sample Users Created:</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr><th>Role</th><th>Email</th><th>Password</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Admin</td><td>admin@safevoice.com</td><td>admin123</td></tr>
                                <tr><td>Survivor</td><td>sarah@example.com</td><td>sarah123</td></tr>
                                <tr><td>Survivor</td><td>john@example.com</td><td>john123</td></tr>
                                <tr><td>Responder</td><td>responder@safevoice.com</td><td>responder123</td></tr>
                            </tbody>
                        </table>
                        
                        <h5>Sample Services Added:</h5>
                        <ul>
                            <li>Central Police Station</li>
                            <li>City General Hospital</li>
                            <li>Hope Shelter</li>
                            <li>Wellness Counseling Center</li>
                            <li>Legal Aid Society</li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <strong>Note:</strong> For security, please delete this install.php file after installation.
                        </div>
                        
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                        <a href="register.php" class="btn btn-secondary">Register New Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>