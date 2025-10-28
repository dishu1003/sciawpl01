<?php
/**
 * Import/Export - Admin Interface
 * Import leads from CSV/Excel and export data
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();
require_admin();
check_session_timeout();

try {
    $pdo = get_pdo_connection();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'import_leads':
                    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                        $csv_file = $_FILES['csv_file']['tmp_name'];
                        $handle = fopen($csv_file, 'r');
                        
                        $imported_count = 0;
                        $skipped_count = 0;
                        $errors = [];
                        
                        // Skip header row
                        $header = fgetcsv($handle);
                        
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            if (count($data) >= 3) { // At least name, email, phone
                                $name = trim($data[0]);
                                $email = trim($data[1]);
                                $phone = trim($data[2] ?? '');
                                $source = trim($data[3] ?? 'Import');
                                $lead_score = trim($data[4] ?? 'COLD');
                                $notes = trim($data[5] ?? '');
                                
                                // Validate email
                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = "Invalid email: $email";
                                    $skipped_count++;
                                    continue;
                                }
                                
                                // Check if lead already exists
                                $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
                                $stmt->execute([$email]);
                                if ($stmt->fetch()) {
                                    $errors[] = "Lead already exists: $email";
                                    $skipped_count++;
                                    continue;
                                }
                                
                                // Insert lead
                                $stmt = $pdo->prepare("
                                    INSERT INTO leads (name, email, phone, source, lead_score, notes, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $stmt->execute([$name, $email, $phone, $source, $lead_score, $notes]);
                                $imported_count++;
                            }
                        }
                        
                        fclose($handle);
                        
                        $_SESSION['success_message'] = "Import completed: $imported_count leads imported, $skipped_count skipped.";
                        if (!empty($errors)) {
                            $_SESSION['import_errors'] = $errors;
                        }
                    }
                    break;
                    
                case 'export_leads':
                    $format = $_POST['export_format'] ?? 'csv';
                    $filters = $_POST['export_filters'] ?? [];
                    
                    // Build query based on filters
                    $where_conditions = [];
                    $params = [];
                    
                    if (!empty($filters['lead_score'])) {
                        $where_conditions[] = "lead_score = ?";
                        $params[] = $filters['lead_score'];
                    }
                    
                    if (!empty($filters['status'])) {
                        $where_conditions[] = "status = ?";
                        $params[] = $filters['status'];
                    }
                    
                    if (!empty($filters['date_from'])) {
                        $where_conditions[] = "DATE(created_at) >= ?";
                        $params[] = $filters['date_from'];
                    }
                    
                    if (!empty($filters['date_to'])) {
                        $where_conditions[] = "DATE(created_at) <= ?";
                        $params[] = $filters['date_to'];
                    }
                    
                    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                    
                    $stmt = $pdo->prepare("
                        SELECT l.*, u.full_name as assigned_to_name
                        FROM leads l
                        LEFT JOIN users u ON l.assigned_to = u.id
                        $where_clause
                        ORDER BY l.created_at DESC
                    ");
                    $stmt->execute($params);
                    $leads = $stmt->fetchAll();
                    
                    if ($format === 'csv') {
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="leads_export_' . date('Y-m-d') . '.csv"');
                        
                        $output = fopen('php://output', 'w');
                        
                        // CSV Header
                        fputcsv($output, [
                            'ID', 'Name', 'Email', 'Phone', 'Source', 'Lead Score', 'Status',
                            'Assigned To', 'Referral Code', 'Follow-up Date', 'Notes', 'Created At'
                        ]);
                        
                        // CSV Data
                        foreach ($leads as $lead) {
                            fputcsv($output, [
                                $lead['id'],
                                $lead['name'],
                                $lead['email'],
                                $lead['phone'],
                                $lead['source'],
                                $lead['lead_score'],
                                $lead['status'],
                                $lead['assigned_to_name'],
                                $lead['referral_code'],
                                $lead['follow_up_date'],
                                $lead['notes'],
                                $lead['created_at']
                            ]);
                        }
                        
                        fclose($output);
                        exit;
                    }
                    break;
            }
        }
    }
    
    // Get import/export statistics
    $stats = [];
    $stats['total_leads'] = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $stats['leads_this_month'] = $pdo->query("
        SELECT COUNT(*) FROM leads 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ")->fetchColumn();
    $stats['leads_last_export'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE DATE(updated_at) = CURDATE()")->fetchColumn();
    
    Logger::info('Import/Export accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in import/export', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $stats = ['total_leads' => 0, 'leads_this_month' => 0, 'leads_last_export' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Import/Export - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .dashboard-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: #333;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .dashboard-nav h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .dashboard-nav .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .dashboard-nav a {
            color: #555;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .dashboard-nav a:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        .dashboard-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card.import::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.export::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.month::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.import { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.export { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.month { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .import-export-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .import-box, .export-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        
        .import-box h3, .export-box h3 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        
        .import-box p, .export-box p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        
        .file-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: block;
            padding: 15px 20px;
            background: #f8f9fa;
            border: 2px dashed #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-label:hover {
            border-color: #667eea;
            background: #f0f8ff;
        }
        
        .file-input-label i {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .csv-template {
            background: #e8f4f8;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }
        
        .csv-template h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .csv-template code {
            background: white;
            padding: 10px;
            border-radius: 5px;
            display: block;
            font-family: monospace;
            font-size: 12px;
        }
        
        .export-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .import-errors {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .import-errors h4 {
            margin-bottom: 10px;
        }
        
        .import-errors ul {
            margin: 0;
            padding-left: 20px;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .import-export-grid {
                grid-template-columns: 1fr;
            }
            
            .export-filters {
                grid-template-columns: 1fr;
            }
            
            .dashboard-nav {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <h1>📊 Import/Export</h1>
        <div class="nav-links">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/leads.php">Leads</a>
            <a href="/admin/team.php">Team</a>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Import Errors -->
        <?php if (isset($_SESSION['import_errors'])): ?>
            <div class="import-errors">
                <h4>Import Errors:</h4>
                <ul>
                    <?php foreach ($_SESSION['import_errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_leads']; ?></div>
                <div class="stat-label">Total Leads</div>
            </div>

            <div class="stat-card import">
                <div class="stat-icon import">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="stat-value"><?php echo $stats['leads_this_month']; ?></div>
                <div class="stat-label">This Month</div>
            </div>

            <div class="stat-card export">
                <div class="stat-icon export">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-value"><?php echo $stats['leads_last_export']; ?></div>
                <div class="stat-label">Updated Today</div>
            </div>
        </div>

        <!-- Import/Export Section -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-exchange-alt"></i>
                Import & Export Data
            </h2>
            
            <div class="import-export-grid">
                <!-- Import Section -->
                <div class="import-box">
                    <h3>
                        <i class="fas fa-upload"></i>
                        Import Leads
                    </h3>
                    <p>Upload a CSV file to import multiple leads at once. Make sure your CSV follows the correct format.</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_leads">
                        
                        <div class="form-group">
                            <label>CSV File</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="csv_file" class="file-input" accept=".csv" required>
                                <label for="csv_file" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to select CSV file</div>
                                    <small>Maximum file size: 10MB</small>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Import Leads
                        </button>
                    </form>
                    
                    <div class="csv-template">
                        <h4>CSV Format:</h4>
                        <code>Name,Email,Phone,Source,Lead Score,Notes
John Doe,john@example.com,+1234567890,Website,HOT,Very interested
Jane Smith,jane@example.com,+1234567891,Referral,WARM,Looking for opportunity</code>
                    </div>
                </div>

                <!-- Export Section -->
                <div class="export-box">
                    <h3>
                        <i class="fas fa-download"></i>
                        Export Leads
                    </h3>
                    <p>Export your leads data to CSV format. You can filter the data before exporting.</p>
                    
                    <form method="POST" id="exportForm">
                        <input type="hidden" name="action" value="export_leads">
                        <input type="hidden" name="export_format" value="csv">
                        
                        <div class="export-filters">
                            <div class="form-group">
                                <label>Lead Score</label>
                                <select name="export_filters[lead_score]" class="form-control">
                                    <option value="">All Scores</option>
                                    <option value="HOT">HOT</option>
                                    <option value="WARM">WARM</option>
                                    <option value="COLD">COLD</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select name="export_filters[status]" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="converted">Converted</option>
                                    <option value="lost">Lost</option>
                                    <option value="follow_up">Follow-up</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="export_filters[date_from]" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="export_filters[date_to]" class="form-control">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                Instructions
            </h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h3 style="color: #333; margin-bottom: 15px;">Import Guidelines:</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>CSV file must have headers in the first row</li>
                        <li>Required columns: Name, Email, Phone</li>
                        <li>Optional columns: Source, Lead Score, Notes</li>
                        <li>Email addresses must be valid and unique</li>
                        <li>Lead Score should be: HOT, WARM, or COLD</li>
                        <li>Maximum file size: 10MB</li>
                        <li>Duplicate emails will be skipped</li>
                    </ul>
                </div>
                
                <div>
                    <h3 style="color: #333; margin-bottom: 15px;">Export Features:</h3>
                    <ul style="color: #666; line-height: 1.8;">
                        <li>Export all leads or filter by criteria</li>
                        <li>Includes all lead information and metadata</li>
                        <li>CSV format compatible with Excel and Google Sheets</li>
                        <li>Can be used for backup and analysis</li>
                        <li>Exported file includes creation timestamps</li>
                        <li>Team member assignments included</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // File input handling
        document.querySelector('.file-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('.file-input-label');
            
            if (file) {
                label.innerHTML = `
                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    <div>Selected: ${file.name}</div>
                    <small>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                `;
                label.style.borderColor = '#28a745';
                label.style.background = '#d4edda';
            } else {
                label.innerHTML = `
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div>Click to select CSV file</div>
                    <small>Maximum file size: 10MB</small>
                `;
                label.style.borderColor = '#e9ecef';
                label.style.background = '#f8f9fa';
            }
        });

        // Export form validation
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            const dateFrom = document.querySelector('input[name="export_filters[date_from]"]').value;
            const dateTo = document.querySelector('input[name="export_filters[date_to]"]').value;
            
            if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                alert('Date From cannot be after Date To');
                e.preventDefault();
            }
        });

        // Auto-fill date inputs for common ranges
        function setDateRange(days) {
            const today = new Date();
            const fromDate = new Date(today.getTime() - (days * 24 * 60 * 60 * 1000));
            
            document.querySelector('input[name="export_filters[date_from]"]').value = fromDate.toISOString().split('T')[0];
            document.querySelector('input[name="export_filters[date_to]"]').value = today.toISOString().split('T')[0];
        }

        // Add quick date range buttons
        const exportForm = document.getElementById('exportForm');
        const quickRanges = document.createElement('div');
        quickRanges.style.cssText = 'margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;';
        
        const ranges = [
            { label: 'Last 7 days', days: 7 },
            { label: 'Last 30 days', days: 30 },
            { label: 'Last 90 days', days: 90 }
        ];
        
        ranges.forEach(range => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-small';
            btn.style.cssText = 'padding: 5px 10px; font-size: 12px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;';
            btn.textContent = range.label;
            btn.onclick = () => setDateRange(range.days);
            quickRanges.appendChild(btn);
        });
        
        exportForm.appendChild(quickRanges);
    </script>
</body>
</html>
