<?php
/**
 * PDF Generator for Safe Haven System
 * Generates professional PDF reports for incidents
 */

// Try to autoload from Composer
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';

// Check if Composer autoload exists
if (!file_exists($vendorAutoload)) {
    // Try manual DOMPDF include
    $dompdfPath = __DIR__ . '/../dompdf/autoload.inc.php';
    if (file_exists($dompdfPath)) {
        require_once $dompdfPath;
    } else {
        // Define fallback functions that show error message
        function generateReportPDF($report_id, $user_role = 'survivor') {
            die('<div style="padding: 50px; text-align: center; font-family: Arial;">
                <h1 style="color: #c62828;">❌ PDF Library Not Installed</h1>
                <p style="font-size: 18px; margin: 20px 0;">DOMPDF library is required for PDF generation.</p>
                <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; max-width: 600px; margin: 20px auto; text-align: left;">
                    <p><strong>To install, run this command in your project root:</strong></p>
                    <code style="background: #333; color: #fff; padding: 10px; display: block; border-radius: 4px;">
                        composer require dompdf/dompdf
                    </code>
                    <p style="margin-top: 15px;"><strong>Or manually download from:</strong></p>
                    <p><a href="https://github.com/dompdf/dompdf/releases" target="_blank">https://github.com/dompdf/dompdf/releases</a></p>
                </div>
                <p><a href="javascript:history.back()" style="color: #2e7d32; text-decoration: underline;">Go Back</a></p>
            </div>');
        }
        function generateAllReportsPDF() {
            die('<div style="padding: 50px; text-align: center; font-family: Arial;">
                <h1 style="color: #c62828;">❌ PDF Library Not Installed</h1>
                <p style="font-size: 18px; margin: 20px 0;">DOMPDF library is required for PDF generation.</p>
                <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; max-width: 600px; margin: 20px auto; text-align: left;">
                    <p><strong>To install, run this command in your project root:</strong></p>
                    <code style="background: #333; color: #fff; padding: 10px; display: block; border-radius: 4px;">
                        composer require dompdf/dompdf
                    </code>
                </div>
                <p><a href="javascript:history.back()" style="color: #2e7d32; text-decoration: underline;">Go Back</a></p>
            </div>');
        }
        return;
    }
} else {
    require_once $vendorAutoload;
}

// Check if DOMPDF is available
if (!class_exists('Dompdf\Dompdf')) {
    // Define fallback functions
    function generateReportPDF($report_id, $user_role = 'survivor') {
        die('<div style="padding: 50px; text-align: center; font-family: Arial;">
            <h1 style="color: #c62828;">❌ DOMPDF Not Found</h1>
            <p style="font-size: 18px; margin: 20px 0;">DOMPDF library is required for PDF generation.</p>
            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; max-width: 600px; margin: 20px auto; text-align: left;">
                <p><strong>To install, run this command in your project root:</strong></p>
                <code style="background: #333; color: #fff; padding: 10px; display: block; border-radius: 4px;">
                    composer require dompdf/dompdf
                </code>
            </div>
            <p><a href="javascript:history.back()" style="color: #2e7d32; text-decoration: underline;">Go Back</a></p>
        </div>');
    }
    function generateAllReportsPDF() {
        die('<div style="padding: 50px; text-align: center; font-family: Arial;">
            <h1 style="color: #c62828;">❌ DOMPDF Not Found</h1>
            <p style="font-size: 18px; margin: 20px 0;">DOMPDF library is required for PDF generation.</p>
            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; max-width: 600px; margin: 20px auto; text-align: left;">
                <p><strong>To install, run this command in your project root:</strong></p>
                <code style="background: #333; color: #fff; padding: 10px; display: block; border-radius: 4px;">
                    composer require dompdf/dompdf
                </code>
            </div>
            <p><a href="javascript:history.back()" style="color: #2e7d32; text-decoration: underline;">Go Back</a></p>
        </div>');
    }
    return;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a PDF report for a specific incident
 * 
 * @param int $report_id The report ID
 * @param string $user_role The role of the user (admin, responder, survivor)
 * @return Dompdf|false The PDF object or false on failure
 */
function generateReportPDF($report_id, $user_role = 'survivor') {
    global $pdo;
    
    // Get report details
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        return false;
    }
    
    // Get user details
    $user = getUser($report['user_id']);
    
    // Get evidence
    $stmt = $pdo->prepare("SELECT * FROM evidence WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $evidence = $stmt->fetchAll();
    
    // Get case updates
    $stmt = $pdo->prepare("SELECT * FROM case_updates WHERE report_id = ? ORDER BY created_at DESC");
    $stmt->execute([$report_id]);
    $updates = $stmt->fetchAll();
    
    // Get assigned responder if any
    $responder_name = 'Not assigned';
    if ($report['assigned_responder_id']) {
        $responder = getUser($report['assigned_responder_id']);
        $responder_name = $responder ? $responder['full_name'] : 'Unknown';
    }
    
    // Get reporter name
    $reporter_name = $report['is_anonymous'] ? 'Anonymous' : ($user ? $user['full_name'] : 'Unknown User');
    
    // Build HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Incident Report #' . $report['id'] . '</title>
        <style>
            body { 
                font-family: "DejaVu Sans", Arial, sans-serif; 
                padding: 40px; 
                color: #333;
                line-height: 1.6;
            }
            .header {
                text-align: center;
                border-bottom: 3px solid #2e7d32;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #1b5e20;
                margin: 0;
                font-size: 28px;
            }
            .header .subtitle {
                color: #666;
                font-size: 14px;
                margin-top: 5px;
            }
            .header .badge {
                display: inline-block;
                padding: 5px 15px;
                background: #1b5e20;
                color: white;
                border-radius: 20px;
                font-size: 14px;
                margin-top: 10px;
            }
            .section {
                margin: 25px 0;
                page-break-inside: avoid;
            }
            .section-title {
                color: #1b5e20;
                font-size: 18px;
                font-weight: bold;
                border-bottom: 2px solid #4caf50;
                padding-bottom: 8px;
                margin-bottom: 15px;
            }
            .detail-grid {
                display: table;
                width: 100%;
                margin: 10px 0;
            }
            .detail-row {
                display: table-row;
            }
            .detail-label {
                display: table-cell;
                font-weight: bold;
                color: #1b5e20;
                padding: 8px 15px 8px 0;
                width: 150px;
                border-bottom: 1px solid #f0f0f0;
            }
            .detail-value {
                display: table-cell;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .description-box {
                background: #f1f8e9;
                padding: 15px;
                border-left: 4px solid #4caf50;
                border-radius: 5px;
                margin: 10px 0;
            }
            .evidence-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
                margin: 15px 0;
            }
            .evidence-item {
                border: 1px solid #a5d6a7;
                border-radius: 5px;
                padding: 10px;
                text-align: center;
            }
            .evidence-item .file-name {
                font-size: 12px;
                color: #2e7d32;
                word-break: break-all;
            }
            .evidence-item .file-icon {
                font-size: 40px;
                color: #4caf50;
                margin: 10px 0;
            }
            .update-item {
                background: #f9f9f9;
                padding: 12px 15px;
                margin: 10px 0;
                border-left: 3px solid #4caf50;
                border-radius: 3px;
            }
            .update-item .update-meta {
                color: #666;
                font-size: 13px;
                margin-bottom: 5px;
            }
            .update-item .update-text {
                margin: 5px 0 0 0;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
            }
            .status-pending { background: #fff3e0; color: #e65100; }
            .status-under-review { background: #e3f2fd; color: #0d47a1; }
            .status-assigned { background: #f3e5f5; color: #6a1b9a; }
            .status-investigating { background: #fff8e1; color: #f57f17; }
            .status-resolved { background: #e8f5e9; color: #1b5e20; }
            .status-closed { background: #f5f5f5; color: #616161; }
            .footer {
                margin-top: 40px;
                text-align: center;
                color: #999;
                font-size: 12px;
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80px;
                opacity: 0.05;
                color: #1b5e20;
                pointer-events: none;
                z-index: 999;
            }
            @page {
                margin: 30px 40px;
            }
            .confidential {
                color: #c62828;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            .role-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: bold;
                margin-left: 5px;
            }
            .role-admin { background: #c62828; color: white; }
            .role-responder { background: #0d47a1; color: white; }
            .role-survivor { background: #2e7d32; color: white; }
            .anonymous-badge {
                display: inline-block;
                padding: 2px 10px;
                background: #f5f5f5;
                color: #666;
                border-radius: 10px;
                font-size: 11px;
            }
        </style>
    </head>
    <body>
        <div class="watermark">CONFIDENTIAL</div>
        
        <div class="header">
            <h1>🔒 Safe Haven - Incident Report</h1>
            <div class="subtitle">Confidential Document</div>
            <div class="subtitle">Report #' . $report['id'] . ' | Generated: ' . date('F d, Y H:i:s') . '</div>
            <div>
                <span class="badge">' . strtoupper($report['status']) . '</span>
                <span class="role-badge role-' . $user_role . '">' . strtoupper($user_role) . ' ACCESS</span>
            </div>
            <div style="margin-top: 10px;">
                <span class="confidential">🔒 CONFIDENTIAL - For Authorized Personnel Only</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">📋 Report Details</div>
            <div class="detail-grid">
                <div class="detail-row">
                    <div class="detail-label">Report ID:</div>
                    <div class="detail-value">#' . $report['id'] . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Title:</div>
                    <div class="detail-value">' . htmlspecialchars($report['title']) . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Category:</div>
                    <div class="detail-value">' . htmlspecialchars($report['category']) . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-' . strtolower(str_replace(' ', '-', $report['status'])) . '">
                            ' . $report['status'] . '
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Incident Date:</div>
                    <div class="detail-value">' . date('F d, Y', strtotime($report['incident_date'])) . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Reported On:</div>
                    <div class="detail-value">' . date('F d, Y H:i:s', strtotime($report['created_at'])) . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Location:</div>
                    <div class="detail-value">' . (htmlspecialchars($report['location']) ?: 'Not specified') . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Submitted By:</div>
                    <div class="detail-value">' . ($report['is_anonymous'] ? '<span class="anonymous-badge">Anonymous</span>' : htmlspecialchars($reporter_name)) . '</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Assigned Responder:</div>
                    <div class="detail-value">' . $responder_name . '</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">📝 Description</div>
            <div class="description-box">
                ' . nl2br(htmlspecialchars($report['description'])) . '
            </div>
        </div>';
    
    // Add evidence section if exists
    if (count($evidence) > 0) {
        $html .= '
        <div class="section">
            <div class="section-title">📎 Evidence Attached (' . count($evidence) . ' files)</div>
            <div class="evidence-grid">';
        
        foreach ($evidence as $item) {
            $file_ext = strtolower(pathinfo($item['file_name'], PATHINFO_EXTENSION));
            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $file_path = defined('UPLOAD_PATH') ? UPLOAD_PATH . $item['file_path'] : $item['file_path'];
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
            $size_formatted = $file_size > 0 ? round($file_size / 1024, 1) . ' KB' : 'Unknown';
            
            $html .= '
                <div class="evidence-item">
                    <div class="file-icon">' . ($is_image ? '🖼️' : '📄') . '</div>
                    <div class="file-name">' . htmlspecialchars($item['file_name']) . '</div>
                    <div style="font-size: 11px; color: #666; margin-top: 5px;">
                        ' . $size_formatted . ' | ' . date('M d, Y', strtotime($item['uploaded_at'])) . '
                    </div>
                </div>';
        }
        
        $html .= '
            </div>
        </div>';
    }
    
    // Add case updates if exists
    if (count($updates) > 0) {
        $html .= '
        <div class="section">
            <div class="section-title">📋 Case Timeline (' . count($updates) . ' updates)</div>';
        
        foreach ($updates as $update) {
            $status_change = $update['status_change'] ? '<br><span style="display: inline-block; background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 10px; font-size: 12px; margin-top: 5px;">Status changed to: ' . $update['status_change'] . '</span>' : '';
            
            $html .= '
            <div class="update-item">
                <div class="update-meta">
                    <strong>' . htmlspecialchars($update['updater_name']) . '</strong>
                    <span style="color: #999;"> - ' . date('F d, Y H:i', strtotime($update['created_at'])) . '</span>
                </div>
                <div class="update-text">' . nl2br(htmlspecialchars($update['update_text'])) . '</div>
                ' . $status_change . '
            </div>';
        }
        
        $html .= '
        </div>';
    }
    
    // Add footer
    $html .= '
        <div class="footer">
            <p>This is a confidential document from the Safe Haven System.</p>
            <p>For authorized personnel only. Generated on ' . date('F d, Y H:i:s') . '</p>
            <p style="color: #c62828; font-weight: bold;">🔒 Unauthorized distribution of this document is strictly prohibited.</p>
            <p style="font-size: 10px; color: #999;">Report #' . $report['id'] . ' | Safe Haven v1.0</p>
        </div>
    </body>
    </html>';
    
    // Generate PDF
    try {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', realpath(__DIR__ . '/..'));
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate a summary PDF of all reports for admin
 * 
 * @return Dompdf|false The PDF object or false on failure
 */
function generateAllReportsPDF() {
    global $pdo;
    
    // Get all reports with reporter info
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                           FROM reports r 
                           LEFT JOIN users u ON r.user_id = u.id 
                           ORDER BY r.created_at DESC");
    $stmt->execute();
    $reports = $stmt->fetchAll();
    
    // Get statistics
    $total_reports = count($reports);
    $status_counts = [];
    $category_counts = [];
    
    foreach ($reports as $report) {
        $status = $report['status'];
        $category = $report['category'];
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
        $category_counts[$category] = ($category_counts[$category] ?? 0) + 1;
    }
    
    // Build HTML for all reports
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>All Reports - Safe Haven</title>
        <style>
            body { font-family: "DejaVu Sans", Arial, sans-serif; padding: 30px; color: #333; }
            .header { text-align: center; border-bottom: 3px solid #2e7d32; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { color: #1b5e20; margin: 0; font-size: 28px; }
            .header .subtitle { color: #666; font-size: 14px; margin-top: 5px; }
            .header .badge-admin { display: inline-block; padding: 5px 15px; background: #c62828; color: white; border-radius: 20px; font-size: 12px; margin-top: 10px; }
            .summary { background: #f1f8e9; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #a5d6a7; }
            .summary-grid { display: table; width: 100%; }
            .summary-item { display: table-cell; text-align: center; padding: 10px; }
            .summary-item .number { font-size: 24px; font-weight: bold; color: #1b5e20; display: block; }
            .summary-item .label { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11px; }
            th { background: #2e7d32; color: white; padding: 10px 8px; text-align: left; font-size: 11px; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }
            .status-pending { background: #fff3e0; color: #e65100; }
            .status-resolved { background: #e8f5e9; color: #1b5e20; }
            .status-under-review { background: #e3f2fd; color: #0d47a1; }
            .status-assigned { background: #f3e5f5; color: #6a1b9a; }
            .status-investigating { background: #fff8e1; color: #f57f17; }
            .status-closed { background: #f5f5f5; color: #616161; }
            .footer { margin-top: 40px; text-align: center; color: #999; font-size: 11px; border-top: 1px solid #ddd; padding-top: 20px; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; opacity: 0.05; color: #1b5e20; pointer-events: none; z-index: 999; }
            .confidential { color: #c62828; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; font-size: 12px; }
            .category-tag { display: inline-block; padding: 2px 8px; background: #e8f5e9; border-radius: 10px; font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="watermark">CONFIDENTIAL</div>
        
        <div class="header">
            <h1>📊 Safe Haven - Complete Report List</h1>
            <div class="subtitle">All Incident Reports Overview</div>
            <div class="subtitle">Generated: ' . date('F d, Y H:i:s') . '</div>
            <div>
                <span class="badge-admin">🔒 ADMIN ACCESS</span>
                <span style="margin-left: 10px; color: #c62828; font-weight: bold;">CONFIDENTIAL</span>
            </div>
        </div>
        
        <div class="summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="number">' . $total_reports . '</span>
                    <span class="label">Total Reports</span>
                </div>';
    
    foreach ($status_counts as $status => $count) {
        $html .= '
                <div class="summary-item">
                    <span class="number">' . $count . '</span>
                    <span class="label">' . $status . '</span>
                </div>';
    }
    
    $html .= '
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Reporter</th>
                    <th>Location</th>
                    <th>Reported</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($reports as $report) {
        $status_class = 'status-' . strtolower(str_replace(' ', '-', $report['status']));
        $reporter = $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['reporter_name'] ?? 'Unknown');
        $location = htmlspecialchars(substr($report['location'], 0, 30)) ?: 'N/A';
        $updated = $report['updated_at'] ? date('M d, Y', strtotime($report['updated_at'])) : 'Never';
        
        $html .= '
                <tr>
                    <td><strong>#' . $report['id'] . '</strong></td>
                    <td>' . htmlspecialchars($report['title']) . '</td>
                    <td><span class="category-tag">' . htmlspecialchars($report['category']) . '</span></td>
                    <td><span class="status-badge ' . $status_class . '">' . $report['status'] . '</span></td>
                    <td>' . $reporter . '</td>
                    <td>' . $location . '</td>
                    <td>' . date('M d, Y', strtotime($report['created_at'])) . '</td>
                    <td>' . $updated . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div style="margin-top: 10px; font-size: 11px; color: #666;">
            <p><strong>Categories:</strong> ' . implode(', ', array_keys($category_counts)) . '</p>
        </div>
        
        <div class="footer">
            <p>This document contains all incident reports from the Safe Haven System.</p>
            <p class="confidential">🔒 Unauthorized distribution of this document is strictly prohibited.</p>
            <p>Generated on ' . date('F d, Y H:i:s') . ' | Total: ' . $total_reports . ' reports</p>
        </div>
    </body>
    </html>';
    
    // Generate PDF
    try {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', realpath(__DIR__ . '/..'));
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        return $dompdf;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}
?>