<?php
/**
 * Add New Lead - Team Member Interface
 * Simple form for team members to add new leads
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();
require_team_access();
check_session_timeout();

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $source = trim($_POST['source']);
        $notes = trim($_POST['notes']);
        $lead_score = $_POST['lead_score'];
        
        if ($name && $email) {
            $stmt = $pdo->prepare("
                INSERT INTO leads (name, email, phone, source, notes, lead_score, assigned_to, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$name, $email, $phone, $source, $notes, $lead_score, $user_id]);
            
            $_SESSION['success_message'] = "Lead added successfully!";
            header('Location: /team/lead-management.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Name and email are required!";
        }
    }
    
    Logger::info('Add lead page accessed', [
        'user_id' => $user_id
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in add lead', [
        'error' => $e->getMessage(),
        'user_id' => $user_id
    ]);
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Add New Lead - My Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #764ba2;
        }
        
        .required {
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
        
        /* Language Toggle */
        .lang-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 15px;
            border-radius: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .lang-toggle button {
            background: none;
            border: none;
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .lang-toggle button.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="lang-toggle">
        <button class="lang-btn" onclick="switchLanguage('en')">EN</button>
        <button class="lang-btn active" onclick="switchLanguage('hi')">हिं</button>
    </div>

    <div class="container">
        <a href="/team/index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span data-en="Back to Dashboard" data-hi="डैशबोर्ड पर वापस जाएं">डैशबोर्ड पर वापस जाएं</span>
        </a>

        <div class="header">
            <h1 data-en="Add New Lead" data-hi="नया लीड जोड़ें">नया लीड जोड़ें</h1>
            <p data-en="Capture a new prospect for your network marketing business" data-hi="अपने नेटवर्क मार्केटिंग व्यवसाय के लिए एक नया प्रॉस्पेक्ट कैप्चर करें">अपने नेटवर्क मार्केटिंग व्यवसाय के लिए एक नया प्रॉस्पेक्ट कैप्चर करें</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label data-en="Full Name *" data-hi="पूरा नाम *">पूरा नाम *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label data-en="Email Address *" data-hi="ईमेल पता *">ईमेल पता *</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter email address">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label data-en="Phone Number" data-hi="फ़ोन नंबर">फ़ोन नंबर</label>
                    <input type="tel" name="phone" class="form-control" placeholder="Enter phone number">
                </div>
                
                <div class="form-group">
                    <label data-en="Lead Source" data-hi="लीड स्रोत">लीड स्रोत</label>
                    <select name="source" class="form-control">
                        <option value="Website" data-en="Website" data-hi="वेबसाइट">वेबसाइट</option>
                        <option value="Social Media" data-en="Social Media" data-hi="सोशल मीडिया">सोशल मीडिया</option>
                        <option value="Referral" data-en="Referral" data-hi="रेफरल">रेफरल</option>
                        <option value="Cold Call" data-en="Cold Call" data-hi="कोल्ड कॉल">कोल्ड कॉल</option>
                        <option value="Event" data-en="Event" data-hi="इवेंट">इवेंट</option>
                        <option value="Networking" data-en="Networking" data-hi="नेटवर्किंग">नेटवर्किंग</option>
                        <option value="Other" data-en="Other" data-hi="अन्य">अन्य</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label data-en="Lead Score *" data-hi="लीड स्कोर *">लीड स्कोर *</label>
                <select name="lead_score" class="form-control" required>
                    <option value="">Select lead score...</option>
                    <option value="HOT" data-en="🔥 HOT - Ready to buy" data-hi="🔥 HOT - खरीदने के लिए तैयार">🔥 HOT - खरीदने के लिए तैयार</option>
                    <option value="WARM" data-en="🌡️ WARM - Interested" data-hi="🌡️ WARM - रुचि है">🌡️ WARM - रुचि है</option>
                    <option value="COLD" data-en="❄️ COLD - Initial contact" data-hi="❄️ COLD - प्रारंभिक संपर्क">❄️ COLD - प्रारंभिक संपर्क</option>
                </select>
            </div>
            
            <div class="form-group">
                <label data-en="Notes" data-hi="नोट्स">नोट्स</label>
                <textarea name="notes" class="form-control" rows="4" placeholder="Any additional information about this lead..."></textarea>
            </div>
            
            <div style="display: flex; gap: 20px; justify-content: center; margin-top: 30px;">
                <a href="/team/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    <span data-en="Cancel" data-hi="रद्द करें">रद्द करें</span>
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <span data-en="Add Lead" data-hi="लीड जोड़ें">लीड जोड़ें</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Language switching functionality
        function switchLanguage(lang) {
            document.querySelectorAll('[data-en][data-hi]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });
            
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            event.target.classList.add('active');
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const nameInput = document.querySelector('input[name="name"]');
            const emailInput = document.querySelector('input[name="email"]');
            const leadScoreSelect = document.querySelector('select[name="lead_score"]');
            
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                if (!nameInput.value.trim()) {
                    showFieldError(nameInput, 'Name is required');
                    isValid = false;
                } else {
                    clearFieldError(nameInput);
                }
                
                if (!emailInput.value.trim()) {
                    showFieldError(emailInput, 'Email is required');
                    isValid = false;
                } else if (!isValidEmail(emailInput.value)) {
                    showFieldError(emailInput, 'Please enter a valid email');
                    isValid = false;
                } else {
                    clearFieldError(emailInput);
                }
                
                if (!leadScoreSelect.value) {
                    showFieldError(leadScoreSelect, 'Lead score is required');
                    isValid = false;
                } else {
                    clearFieldError(leadScoreSelect);
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
            
            function showFieldError(field, message) {
                field.style.borderColor = '#e74c3c';
                field.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                
                let errorDiv = field.parentNode.querySelector('.field-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'field-error';
                    errorDiv.style.color = '#e74c3c';
                    errorDiv.style.fontSize = '14px';
                    errorDiv.style.marginTop = '5px';
                    field.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
            }
            
            function clearFieldError(field) {
                field.style.borderColor = '#e9ecef';
                field.style.boxShadow = 'none';
                
                const errorDiv = field.parentNode.querySelector('.field-error');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        });
    </script>
</body>
</html>
