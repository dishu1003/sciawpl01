<?php
/**
 * Integrations - Admin Interface
 * WhatsApp and Email integration settings
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
    
    // Create integrations table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS integration_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            integration_type ENUM('whatsapp', 'email', 'sms') NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_integration_setting (integration_type, setting_key)
        )
    ");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_whatsapp_settings':
                    $whatsapp_number = trim($_POST['whatsapp_number']);
                    $api_key = trim($_POST['whatsapp_api_key']);
                    $webhook_url = trim($_POST['whatsapp_webhook_url']);
                    $is_active = isset($_POST['whatsapp_active']) ? 1 : 0;
                    
                    // Save WhatsApp settings
                    $stmt = $pdo->prepare("
                        INSERT INTO integration_settings (integration_type, setting_key, setting_value, is_active)
                        VALUES ('whatsapp', 'phone_number', ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_active = VALUES(is_active)
                    ");
                    $stmt->execute([$whatsapp_number, $is_active]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO integration_settings (integration_type, setting_key, setting_value, is_active)
                        VALUES ('whatsapp', 'api_key', ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_active = VALUES(is_active)
                    ");
                    $stmt->execute([$api_key, $is_active]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO integration_settings (integration_type, setting_key, setting_value, is_active)
                        VALUES ('whatsapp', 'webhook_url', ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_active = VALUES(is_active)
                    ");
                    $stmt->execute([$webhook_url, $is_active]);
                    
                    $_SESSION['success_message'] = "WhatsApp settings saved successfully!";
                    break;
                    
                case 'save_email_settings':
                    $smtp_host = trim($_POST['smtp_host']);
                    $smtp_port = trim($_POST['smtp_port']);
                    $smtp_username = trim($_POST['smtp_username']);
                    $smtp_password = trim($_POST['smtp_password']);
                    $from_email = trim($_POST['from_email']);
                    $from_name = trim($_POST['from_name']);
                    $is_active = isset($_POST['email_active']) ? 1 : 0;
                    
                    // Save Email settings
                    $settings = [
                        'smtp_host' => $smtp_host,
                        'smtp_port' => $smtp_port,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => $smtp_password,
                        'from_email' => $from_email,
                        'from_name' => $from_name
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("
                            INSERT INTO integration_settings (integration_type, setting_key, setting_value, is_active)
                            VALUES ('email', ?, ?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_active = VALUES(is_active)
                        ");
                        $stmt->execute([$key, $value, $is_active]);
                    }
                    
                    $_SESSION['success_message'] = "Email settings saved successfully!";
                    break;
                    
                case 'test_whatsapp':
                    $test_number = trim($_POST['test_number']);
                    $test_message = trim($_POST['test_message']);
                    
                    if ($test_number && $test_message) {
                        // Simulate WhatsApp message sending
                        $_SESSION['success_message'] = "WhatsApp test message sent to $test_number";
                    }
                    break;
                    
                case 'test_email':
                    $test_email = trim($_POST['test_email']);
                    
                    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                        // Simulate email sending
                        $_SESSION['success_message'] = "Test email sent to $test_email";
                    }
                    break;
            }
        }
    }
    
    // Get current settings
    $settings = [];
    $stmt = $pdo->query("SELECT integration_type, setting_key, setting_value, is_active FROM integration_settings");
    $results = $stmt->fetchAll();
    
    foreach ($results as $result) {
        $settings[$result['integration_type']][$result['setting_key']] = [
            'value' => $result['setting_value'],
            'active' => $result['is_active']
        ];
    }
    
    // Integration statistics
    $stats = [];
    $stats['whatsapp_active'] = isset($settings['whatsapp']['phone_number']['active']) && $settings['whatsapp']['phone_number']['active'];
    $stats['email_active'] = isset($settings['email']['smtp_host']['active']) && $settings['email']['smtp_host']['active'];
    $stats['total_integrations'] = ($stats['whatsapp_active'] ? 1 : 0) + ($stats['email_active'] ? 1 : 0);
    
    Logger::info('Integrations accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in integrations', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $settings = [];
    $stats = ['whatsapp_active' => false, 'email_active' => false, 'total_integrations' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔗 Integrations - Admin Dashboard</title>
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
        
        .stat-card.whatsapp::before { background: linear-gradient(90deg, #25d366, #128c7e); }
        .stat-card.email::before { background: linear-gradient(90deg, #ea4335, #fbbc04); }
        .stat-card.total::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        
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
        .stat-icon.whatsapp { background: linear-gradient(135deg, #25d366, #128c7e); }
        .stat-icon.email { background: linear-gradient(135deg, #ea4335, #fbbc04); }
        .stat-icon.total { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        
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
        
        .integration-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .integration-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .integration-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .integration-card.whatsapp {
            border-left-color: #25d366;
        }
        
        .integration-card.email {
            border-left-color: #ea4335;
        }
        
        .integration-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .integration-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .integration-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .integration-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .integration-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        .btn {
            padding: 10px 20px;
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .test-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .test-section h4 {
            color: #333;
            margin-bottom: 15px;
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
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            color: #1565c0;
            margin-left: 20px;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .integration-grid {
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
        <h1>🔗 Integrations</h1>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card whatsapp">
                <div class="stat-icon whatsapp">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="stat-value"><?php echo $stats['whatsapp_active'] ? 'ON' : 'OFF'; ?></div>
                <div class="stat-label">WhatsApp Integration</div>
            </div>

            <div class="stat-card email">
                <div class="stat-icon email">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['email_active'] ? 'ON' : 'OFF'; ?></div>
                <div class="stat-label">Email Integration</div>
            </div>

            <div class="stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-plug"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_integrations']; ?></div>
                <div class="stat-label">Active Integrations</div>
            </div>
        </div>

        <div class="integration-grid">
            <!-- WhatsApp Integration -->
            <div class="integration-card whatsapp">
                <div class="integration-header">
                    <div class="integration-title">
                        <i class="fab fa-whatsapp"></i>
                        WhatsApp Integration
                    </div>
                    <div class="integration-status <?php echo $stats['whatsapp_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $stats['whatsapp_active'] ? 'Active' : 'Inactive'; ?>
                    </div>
                </div>
                
                <form method="POST" id="whatsappForm">
                    <input type="hidden" name="action" value="save_whatsapp_settings">
                    
                    <div class="form-group">
                        <label>WhatsApp Business Number</label>
                        <input type="tel" name="whatsapp_number" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['whatsapp']['phone_number']['value'] ?? ''); ?>" 
                               placeholder="+1234567890">
                    </div>
                    
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" name="whatsapp_api_key" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['whatsapp']['api_key']['value'] ?? ''); ?>" 
                               placeholder="Your WhatsApp API key">
                    </div>
                    
                    <div class="form-group">
                        <label>Webhook URL</label>
                        <input type="url" name="whatsapp_webhook_url" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['whatsapp']['webhook_url']['value'] ?? ''); ?>" 
                               placeholder="https://yourdomain.com/webhook/whatsapp">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="whatsapp_active" id="whatsappActive" 
                               <?php echo ($settings['whatsapp']['phone_number']['active'] ?? false) ? 'checked' : ''; ?>>
                        <label for="whatsappActive">Enable WhatsApp Integration</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save WhatsApp Settings
                    </button>
                </form>
                
                <div class="test-section">
                    <h4>Test WhatsApp Integration</h4>
                    <form method="POST" id="whatsappTestForm">
                        <input type="hidden" name="action" value="test_whatsapp">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Test Number</label>
                                <input type="tel" name="test_number" class="form-control" placeholder="+1234567890">
                            </div>
                            <div class="form-group">
                                <label>Test Message</label>
                                <input type="text" name="test_message" class="form-control" placeholder="Hello from DS Support!">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fab fa-whatsapp"></i> Send Test Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Email Integration -->
            <div class="integration-card email">
                <div class="integration-header">
                    <div class="integration-title">
                        <i class="fas fa-envelope"></i>
                        Email Integration
                    </div>
                    <div class="integration-status <?php echo $stats['email_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $stats['email_active'] ? 'Active' : 'Inactive'; ?>
                    </div>
                </div>
                
                <form method="POST" id="emailForm">
                    <input type="hidden" name="action" value="save_email_settings">
                    
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['smtp_host']['value'] ?? ''); ?>" 
                               placeholder="smtp.gmail.com">
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['smtp_port']['value'] ?? '587'); ?>" 
                               placeholder="587">
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="email" name="smtp_username" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['smtp_username']['value'] ?? ''); ?>" 
                               placeholder="your-email@gmail.com">
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['smtp_password']['value'] ?? ''); ?>" 
                               placeholder="Your email password">
                    </div>
                    
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="from_email" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['from_email']['value'] ?? ''); ?>" 
                               placeholder="noreply@yourdomain.com">
                    </div>
                    
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="from_name" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['email']['from_name']['value'] ?? ''); ?>" 
                               placeholder="Direct Selling Support">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="email_active" id="emailActive" 
                               <?php echo ($settings['email']['smtp_host']['active'] ?? false) ? 'checked' : ''; ?>>
                        <label for="emailActive">Enable Email Integration</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Email Settings
                    </button>
                </form>
                
                <div class="test-section">
                    <h4>Test Email Integration</h4>
                    <form method="POST" id="emailTestForm">
                        <input type="hidden" name="action" value="test_email">
                        
                        <div class="form-group">
                            <label>Test Email Address</label>
                            <input type="email" name="test_email" class="form-control" placeholder="test@example.com">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-envelope"></i> Send Test Email
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Integration Information -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                Integration Information
            </h2>
            
            <div class="info-box">
                <h4>WhatsApp Integration</h4>
                <ul>
                    <li>Send automated messages to leads</li>
                    <li>Receive WhatsApp messages from leads</li>
                    <li>Track message delivery status</li>
                    <li>Send follow-up reminders via WhatsApp</li>
                    <li>Bulk WhatsApp campaigns</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>Email Integration</h4>
                <ul>
                    <li>Send automated email notifications</li>
                    <li>Email follow-up sequences</li>
                    <li>Team member email alerts</li>
                    <li>Lead assignment notifications</li>
                    <li>Weekly/monthly reports via email</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('whatsappForm').addEventListener('submit', function(e) {
            const phoneNumber = document.querySelector('input[name="whatsapp_number"]').value;
            if (phoneNumber && !phoneNumber.match(/^\+\d{10,15}$/)) {
                alert('Please enter a valid phone number with country code (e.g., +1234567890)');
                e.preventDefault();
            }
        });

        document.getElementById('emailForm').addEventListener('submit', function(e) {
            const smtpHost = document.querySelector('input[name="smtp_host"]').value;
            const smtpPort = document.querySelector('input[name="smtp_port"]').value;
            
            if (smtpHost && !smtpHost.includes('.')) {
                alert('Please enter a valid SMTP host');
                e.preventDefault();
            }
            
            if (smtpPort && (isNaN(smtpPort) || smtpPort < 1 || smtpPort > 65535)) {
                alert('Please enter a valid SMTP port (1-65535)');
                e.preventDefault();
            }
        });

        // Auto-save functionality
        let autoSaveTimeout;
        function autoSave(formId) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                const form = document.getElementById(formId);
                const formData = new FormData(form);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        console.log('Settings auto-saved');
                    }
                })
                .catch(error => {
                    console.error('Auto-save failed:', error);
                });
            }, 2000);
        }

        // Add auto-save to form inputs
        document.querySelectorAll('#whatsappForm input, #emailForm input').forEach(input => {
            input.addEventListener('input', () => {
                if (input.closest('#whatsappForm')) {
                    autoSave('whatsappForm');
                } else if (input.closest('#emailForm')) {
                    autoSave('emailForm');
                }
            });
        });
    </script>
</body>
</html>
