<?php
/**
 * Retiree Success Landing Page
 * Designed for retirees looking for meaningful opportunities
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
    $age = trim($_POST['age'] ?? '');
    $background = trim($_POST['background'] ?? '');
    
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
                VALUES (?, ?, ?, 'landing_retiree', ?, ?, 'COLD', ?, NOW())
            ");
            $notes = "City: $city, Age: $age, Background: $background";
            $stmt->execute([$name, $email, $phone, $_SESSION['referral_code'] ?? null, $assigned_to, $notes]);
            
            // Clear referral session
            unset($_SESSION['referral_code']);
            
            // Redirect to WhatsApp with hidden referral
            $whatsapp_message = "Hi! I'm interested in the retirement opportunity you shared. I'm $name, $age years old from $city. I have experience in $background and want to stay active and earn. Can you tell me more?";
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
    <title>👴 Retiree Success - Spartan Community India</title>
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
            background: #fff5f5;
            border-radius: 12px;
            border-left: 4px solid #ff6b6b;
        }
        
        .benefit-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
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
        
        .form-section h2 {
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
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
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
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
        }
        
        .testimonials {
            margin-top: 30px;
        }
        
        .testimonial {
            background: #fff5f5;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #ee5a24;
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
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
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
            <h1>👴 Retiree Success Program</h1>
            <p>Stay Active, Stay Earning - Your Experience is Your Asset</p>
        </div>
        
        <div class="main-content">
            <div class="content-section">
                <div class="benefits">
                    <h2ye>Perfect for Experienced Retirees</h2>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Value Your Experience</h3>
                            <p>Your years of experience are your biggest asset. Share your wisdom!</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Flexible Schedule</h3>
                            <p>Work at your own pace. No pressure, just pure enjoyment!</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Extra Income</h3>
                            <p>Earn ₹15,000-60,000 monthly to support your golden years</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Stay Active</h3>
                            <p>Keep your mind sharp and stay socially connected</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Mentor Others</h3>
                            <p>Guide younger people and share your valuable experience</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="benefit-text">
                            <h3>Work from Home</h3>
                            <p>No need to travel. Work comfortably from your home</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number">300+</div>
                        <div class="stat-label">Retiree Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₹35K</div>
                        <div class="stat-label">Avg Monthly Income</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">99%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
                
                <div class="testimonials">
                    <h3 style="text-align: center; margin-bottom: 20px; color: #333;">Success Stories</h3>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "At 65, I thought my working days were over. But this opportunity gave me a new purpose. I'm earning ₹40,000 monthly and helping others succeed."
                        </div>
                        <div class="testimonial-author">- Ramesh, 65, Former Bank Manager</div>
                    </div>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "Retirement was boring until I found this. Now I'm busy, happy, and financially independent. My experience is finally being valued!"
                        </div>
                        <div class="testimonial-author">- Sushila, 62, Former Teacher</div>
                    </div>
                </div>
            </div>
            
            <div class="content-section">
                <h2>Start Your Second Career</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="retireeForm">
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
                            <label for="age">Age</label>
                            <select id="age" name="age" class="form-control">
                                <option value="">Select Age Range</option>
                                <option value="50-55">50-55 Years</option>
                                <option value="55-60">55-60 Years</option>
                                <option value="60-65">60-65 Years</option>
                                <option value="65+">65+ Years</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="background">Professional Background</label>
                            <select id="background" name="background" class="form-control">
                                <option value="">Select Background</option>
                                <option value="Government Service">Government Service</option>
                                <option value="Private Sector">Private Sector</option>
                                <option value="Business">Business</option>
                                <option value="Education">Education</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Banking">Banking</option>
                                <option value="Other">Other</option>
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
                    <p>🌟 Your experience is valuable to us</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('retireeForm').addEventListener('submit', function(e) {
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
