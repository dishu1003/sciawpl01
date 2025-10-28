<?php
/**
 * Job Seeker Opportunity Landing Page
 * Designed for unemployed people looking for opportunities
 */

session_start();

// Handle referral code
if (isset($_GET['ref'])) {
    $_SESSION['referral_code'] = $_GET['ref'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    
    if ($name && $email && $phone) {
        // Include database connection
        require_once 'includes/init.php';
        
        try {
            $pdo = get_pdo_connection();
            
            // Find team member by referral code
            $assigned_to = null;
            if (isset($_SESSION['referral_code'])) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'team'");
                $stmt->execute([$_SESSION['referral_code']]);
                $user = $stmt->fetch();
                if ($user) {
                    $assigned_to = $user['id'];
                }
            }
            
            // Insert lead
            $stmt = $pdo->prepare("
                INSERT INTO leads (name, email, phone, source, referral_code, assigned_to, lead_score, notes, created_at)
                VALUES (?, ?, ?, 'landing_jobless', ?, ?, 'COLD', ?, NOW())
            ");
            $notes = "City: $city, Education: $education, Experience: $experience";
            $stmt->execute([$name, $email, $phone, $_SESSION['referral_code'] ?? null, $assigned_to, $notes]);
            
            // Clear referral session
            unset($_SESSION['referral_code']);
            
            // Redirect to WhatsApp with hidden referral
            $whatsapp_message = "Hi! I'm interested in the job opportunity you shared. I'm $name from $city. I have $education and $experience experience. I'm looking for a stable income source. Can you tell me more?";
            $whatsapp_url = "https://wa.me/919876543210?text=" . urlencode($whatsapp_message);
            
            header("Location: $whatsapp_url");
            exit;
            
        } catch (Exception $e) {
            $error_message = "Something went wrong. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💼 Job Seeker Opportunity - Spartan Community India</title>
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
            background: linear-gradient(135deg, #54a0ff 0%, #5f27cd 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.3rem;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        
        .content-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .benefits {
            margin-bottom: 30px;
        }
        
        .benefits h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f8ff;
            border-radius: 12px;
            border-left: 4px solid #54a0ff;
        }
        
        .benefit-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #54a0ff, #5f27cd);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .benefit-text h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .benefit-text p {
            color: #666;
            font-size: 0.9rem;
        }
        
        strong>.form-section h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
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
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #54a0ff;
            box-shadow: 0 0 0 3px rgba(84, 160, 255, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #54a0ff, #5f27cd);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(84, 160, 255, 0.3);
        }
        
        .testimonials {
            margin-top: 30px;
        }
        
        .testimonial {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #5f27cd;
        }
        
        .testimonial-text {
            font-style: italic;
            color: #555;
            margin-bottom: 10px;
        }
        
        .testimonial-author {
            font-weight: 600;
            color: #333;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #54a0ff, #5f27cd);
            color: white;
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.5rem;
            }
            
            .main-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .content-section {
                padding: 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💼 Job Seeker Opportunity</h1>
            <p>Turn Your Job Search into Success - Start Earning Today</p>
        </div>
        
        <div class="main-content">
            <div class="content-section">
                <div class="benefits">
                    <h2>Perfect for Job Seekers</h2>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Immediate Income</h3>
                            <p>Start earning from day one. No waiting for job interviews!</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Growth Opportunity</h3>
                            <p>Build a career while looking for traditional jobs</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Good Income</h3>
                            <p>Earn ₹12,000-45,000 monthly while job hunting</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Networking</h3>
                            <p>Meet professionals who can help with job referrals</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Skill Development</h3>
                            <p>Learn valuable skills that employers want</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Flexible Schedule</h3>
                            <p>Work around your job search and interviews</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number">600+</div>
                        <div class="stat-label">Job Seeker Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₹22K</div>
                        <div class="stat-label">Avg Monthly Income</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">96%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
                
                <div class="testimonials">
                    <h3 style="text-align: center; margin-bottom: 20px; color: #333;">Success Stories</h3>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "I was unemployed for 8 months. This opportunity gave me hope and income. Now I'm earning ₹30,000 monthly and got a job referral too!"
                        </div>
                        <div class="testimonial-author">- Amit, Engineering Graduate</div>
                    </div>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "This helped me stay positive during my job search. The income supported my family and the networking helped me get interviews."
                        </div>
                        <div class="testimonial-author">- Priya, MBA Graduate</div>
                    </div>
                </div>
            </div>
            
            <div class="content-section">
                <h2>Start Your Success Journey</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="jobSeekerForm">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="education">Education Level</label>
                            <select id="education" name="education" class="form-control">
                                <option value="">Select Education</option>
                                <option value="10th Pass">10th Pass</option>
                                <option value="12th Pass">12th Pass</option>
                                <option value="Graduate">Graduate</option>
                                <option value="Post Graduate">Post Graduate</option>
                                <option value="Professional Degree">Professional Degree</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="experience">Work Experience</label>
                            <select id="experience" name="experience" class="form-control">
                                <option value="">Select Experience</option>
                                <option value="No Experience">No Experience</option>
                                <option value="0-1 Years">0-1 Years</option>
                                <option value="1-3 Years">1-3 Years</option>
                                <option value="3-5 Years">3-5 Years</option>
                                <option value="5+ Years">5+ Years</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fab fa-whatsapp"></i>
                        Get Started on WhatsApp
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                    <p>🔒 Your information is secure and will not be shared</p>
                    <p>📱 We'll contact you within 24 hours</p>
                    <p>💼 Turn your job search into success</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('jobSeekerForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const email = document.getElementById('email').value;
            
            // Validate phone number
            if (!phone.match(/^[6-9]\d{9}$/)) {
                alert('Please enter a valid 10-digit phone number');
                e.preventDefault();
                return;
            }
            
            // Validate email
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return;
            }
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
