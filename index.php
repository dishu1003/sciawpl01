<?php
// --- START: CONFIG AND INITIALIZATION ---
// This block is updated to explicitly include both files, assuming they define the necessary 
// functions (like get_pdo_connection()) and start the session.
// NOTE: You must ensure these files exist at the paths shown below.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/init.php'; 

// 2. Ensure session is running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- END: CONFIG AND INITIALIZATION ---

// Handle referral code
$ref = $_GET['ref'] ?? ($_SESSION['ref'] ?? null);
if ($ref) { 
    $_SESSION['ref'] = $ref; 
}

// Initialize error message variable
$error_message = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_successful = false;

    // Check for required variables before proceeding
    if (function_exists('get_pdo_connection')) {
        try {
            $pdo = get_pdo_connection();
            
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $city = trim($_POST['city']);
            $referral_code = $_SESSION['ref'] ?? null;
            
            if ($name && $email && $phone && $city) {
                // Check if referral code exists and get assigned user
                $assigned_to = null;
                if ($referral_code) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'team' AND status = 'active'");
                    $stmt->execute([$referral_code]);
                    $assigned_to = $stmt->fetchColumn();
                }
                
                // Insert lead
                $stmt = $pdo->prepare("
                    INSERT INTO leads (name, email, phone, city, source, referral_code, assigned_to, lead_score, status, created_at) 
                    VALUES (?, ?, ?, ?, 'Website', ?, ?, 'COLD', 'active', NOW())
                ");
                
                // Check if execution was successful
                if ($stmt->execute([$name, $email, $phone, $city, $referral_code, $assigned_to])) {
                    $submission_successful = true;
                }
                
                if ($submission_successful) {
                    // Clear referral session
                    unset($_SESSION['ref']);
                    
                    // Create WhatsApp message
                    $whatsapp_message = urlencode("Know More");
                    $whatsapp_url = "https://wa.me/919165154400?text={$whatsapp_message}";
                    
                    // Redirect to WhatsApp (JS based)
                    echo "<script>
                    window.location.href = '{$whatsapp_url}';
                    </script>";
                    exit;
                } else {
                     $error_message = 'डेटाबेस में लीड सेव नहीं हो सकी। कृपया पुनः प्रयास करें। (Could not save lead to the database. Please try again.)';
                }
            } else {
                 $error_message = 'कृपया सुनिश्चित करें कि आपने सभी फ़ील्ड सही ढंग से भरे हैं। (Please ensure all fields are filled correctly.)';
            }
        } catch (PDOException $e) {
            error_log('Lead submission error: ' . $e->getMessage());
            // Check for common Duplicate entry error (error code 23000 in MySQL)
            if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                 $error_message = 'यह ईमेल या फ़ोन नंबर पहले से ही पंजीकृत है। (This email or phone number is already registered.)';
            } else {
                 $error_message = 'क्षमा करें, सबमिशन में तकनीकी त्रुटि आई है। कृपया व्यवस्थापक से संपर्क करें। (Sorry, a technical error occurred during submission. Please contact administrator.)';
            }
        }
    } else {
        error_log('get_pdo_connection() function is not defined. Check includes/init.php.');
        $error_message = 'Configuration Error: Database connection function is missing (check includes/init.php).';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>The Proven System That Helped 1,247+ People Escape the 9-5 Trap</title>
    <meta name="description" content="Discover the exact blueprint used by top earners to build a 6-figure income from home—without experience, inventory, or tech skills.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* General Reset and Typography */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height:1.6;
            color:#2c3e50;
            background:#fff;
            overflow-x:hidden;
        }
        .container { max-width:1200px; margin:0 auto; padding:0 20px; }
        
        /* Message Box Styling */
        #messageBox {
            display: none; 
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #ef4444; /* Red background for error */
            color: white;
            padding: 15px 20px;
            text-align: center;
            font-weight: 600;
            z-index: 2000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e22ce 100%);
            color:#fff;
            padding:60px 20px 80px;
            position:relative;
            overflow:hidden;
        }
        .hero::before {
            content:'';
            position:absolute;
            top:-50%;
            right:-20%;
            width:600px;
            height:600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
            border-radius:50%;
        }
        .hero-content { position:relative; z-index:2; text-align:center; max-width:900px; margin:0 auto; }
        .hero h1 {
            font-size: clamp(32px, 6vw, 56px);
            font-weight:900;
            line-height:1.1;
            margin-bottom:20px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .hero .subheadline {
            font-size: clamp(18px, 3vw, 24px);
            font-weight:500;
            margin-bottom:30px;
            opacity:0.95;
        }
        .cta-primary {
            display:inline-block;
            background:#10b981;
            color:#fff;
            padding:18px 40px;
            border-radius:12px;
            font-size:1.2rem;
            font-weight:700;
            text-decoration:none;
            box-shadow: 0 10px 30px rgba(16,185,129,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            border:none;
            cursor:pointer;
        }
        .cta-primary:hover { transform:translateY(-2px); box-shadow: 0 15px 40px rgba(16,185,129,0.5); }
        .cta-primary:active { transform:translateY(0); }
        .trust-bar {
            display:flex;
            justify-content:center;
            align-items:center;
            gap:30px;
            margin-top:40px;
            flex-wrap:wrap;
        }
        .trust-item {
            display:flex;
            align-items:center;
            gap:10px;
            background:rgba(255,255,255,0.1);
            padding:10px 20px;
            border-radius:8px;
            backdrop-filter:blur(10px);
        }
        .trust-item svg { width:24px; height:24px; fill:#10b981; }
        
        /* Video Section */
        .video-section {
            background:#f9fafb;
            padding:80px 20px;
            text-align:center;
        }
        .video-wrapper {
            max-width:900px;
            margin:0 auto;
            background:#000;
            border-radius:16px;
            overflow:hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position:relative;
            padding-bottom:56.25%; /* 16:9 */
        }
        .video-wrapper iframe {
            position:absolute;
            top:0;
            left:0;
            width:100%;
            height:100%;
        }
        
        /* Social Proof & Stats */
        .social-proof {
            background:#fff;
            padding:60px 20px;
        }
        .stats {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
            gap:30px;
            text-align:center;
            margin-bottom:60px;
        }
        .stat-box {
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff;
            padding:30px 20px;
            border-radius:12px;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        .stat-number {
            font-size:3rem;
            font-weight:900;
            line-height:1;
            margin-bottom:10px;
        }
        .stat-label {
            font-size:1rem;
            opacity:0.9;
        }
        
        /* Testimonials */
        .testimonials {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));
            gap:30px;
        }
        .testimonial {
            background:#f9fafb;
            padding:30px;
            border-radius:12px;
            border-left:4px solid #10b981;
        }
        .testimonial-text {
            font-style:italic;
            margin-bottom:20px;
            color:#374151;
        }
        .testimonial-author {
            display:flex;
            align-items:center;
            gap:15px;
        }
        .testimonial-avatar {
            width:50px;
            height:50px;
            border-radius:50%;
            background:linear-gradient(135deg, #667eea, #764ba2);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-weight:700;
            font-size:1.2rem;
        }
        .testimonial-name {
            font-weight:700;
            color:#1f2937;
        }
        .testimonial-role {
            font-size:0.9rem;
            color:#6b7280;
        }
        
        /* Pain Points */
        .pain-section {
            background:linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color:#fff;
            padding:80px 20px;
        }
        .pain-section h2 {
            font-size: clamp(28px, 5vw, 42px);
            text-align:center;
            margin-bottom:50px;
            font-weight:800;
        }
        .pain-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
            gap:30px;
        }
        .pain-card {
            background:rgba(255,255,255,0.05);
            padding:30px;
            border-radius:12px;
            border:1px solid rgba(255,255,255,0.1);
            backdrop-filter:blur(10px);
        }
        .pain-card h3 {
            color:#ef4444;
            font-size:1.5rem;
            margin-bottom:15px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .pain-card p {
            color:#d1d5db;
            line-height:1.8;
        }
        
        /* Solution */
        .solution-section {
            background:#fff;
            padding:80px 20px;
        }
        .solution-section h2 {
            font-size: clamp(28px, 5vw, 42px);
            text-align:center;
            margin-bottom:20px;
            font-weight:800;
            color:#1f2937;
        }
        .solution-intro {
            text-align:center;
            max-width:800px;
            margin:0 auto 50px;
            font-size:1.2rem;
            color:#4b5563;
        }
        .benefits {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));
            gap:30px;
        }
        .benefit-card {
            background:#f9fafb;
            padding:30px;
            border-radius:12px;
            border-top:4px solid #10b981;
            transition: transform 0.2s;
        }
        .benefit-card:hover { transform:translateY(-5px); }
        .benefit-icon {
            width:60px;
            height:60px;
            background:linear-gradient(135deg, #10b981, #059669);
            border-radius:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin-bottom:20px;
            font-size:1.8rem;
        }
        .benefit-card h3 {
            font-size:1.3rem;
            margin-bottom:10px;
            color:#1f2937;
        }
        .benefit-card p {
            color:#6b7280;
            line-height:1.8;
        }
        
        /* Form Section */
        .form-section {
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding:80px 20px;
        }
        .form-container {
            max-width:700px;
            margin:0 auto;
            background:#fff;
            padding:50px;
            border-radius:16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative; /* For Message Box positioning */
        }
        .form-header {
            text-align:center;
            margin-bottom:40px;
        }
        .form-header h2 {
            font-size: clamp(24px, 4vw, 36px);
            font-weight:800;
            color:#1f2937;
            margin-bottom:10px;
        }
        .form-header p {
            color:#6b7280;
            font-size:1.1rem;
        }
        .form-group {
            margin-bottom:25px;
        }
        .form-group label {
            display:block;
            font-weight:600;
            margin-bottom:8px;
            color:#374151;
        }
        .form-group input,
        .form-group select {
            width:100%;
            padding:14px 16px;
            border:2px solid #e5e7eb;
            border-radius:10px;
            font-size:1rem;
            font-family:inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline:none;
            border-color:#667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .hint {
            display:block;
            margin-top:6px;
            font-size:0.9rem;
            color:#6b7280;
        }
        .submit-btn {
            width:100%;
            background:#10b981;
            color:#fff;
            padding:18px;
            border:none;
            border-radius:10px;
            font-size:1.2rem;
            font-weight:700;
            cursor:pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 10px 30px rgba(16,185,129,0.3);
        }
        .submit-btn:hover { transform:translateY(-2px); box-shadow: 0 15px 40px rgba(16,185,129,0.4); }
        .submit-btn:active { transform:translateY(0); }
        .form-footer {
            text-align:center;
            margin-top:20px;
            color:#6b7280;
            font-size:0.9rem;
        }
        
        /* Urgency Bar */
        .urgency-bar {
            background:#ef4444;
            color:#fff;
            padding:15px 20px;
            text-align:center;
            font-weight:700;
            position:sticky;
            top:0;
            z-index:1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .countdown {
            display:inline-flex;
            gap:15px;
            margin-left:15px;
        }
        .countdown-item {
            background:rgba(255,255,255,0.2);
            padding:5px 10px;
            border-radius:6px;
            font-size:1.1rem;
        }
        
        /* Live Counter */
        .live-counter {
            background:#fef3c7;
            border-left:4px solid #f59e0b;
            padding:15px 20px;
            margin:30px 0;
            border-radius:8px;
            display:flex;
            align-items:center;
            gap:15px;
        }
        .live-dot {
            width:12px;
            height:12px;
            background:#ef4444;
            border-radius:50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity:1; }
            50% { opacity:0.3; }
        }
        
        /* Floating Action Button */
        .floating-btn {
            position:fixed;
            bottom:30px;
            right:30px;
            background:#10b981;
            color:#fff;
            padding:18px 30px;
            border-radius:50px;
            font-size:1.1rem;
            font-weight:700;
            text-decoration:none;
            box-shadow: 0 10px 40px rgba(16,185,129,0.5);
            z-index:999;
            transition: transform 0.3s, box-shadow 0.3s;
            animation: floatBounce 2s infinite;
        }
        .floating-btn:hover {
            transform:translateY(-5px);
            box-shadow: 0 15px 50px rgba(16,185,129,0.6);
        }
        @keyframes floatBounce {
            0%, 100% { transform:translateY(0); }
            50% { transform:translateY(-10px); }
        }
        
        /* Sticky Header CTA */
        .sticky-header-cta {
            position:fixed;
            top:60px;
            left:50%;
            transform:translateX(-50%);
            background:#fff;
            padding:12px 30px;
            border-radius:50px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.2);
            z-index:998;
            opacity:0;
            pointer-events:none;
            transition: opacity 0.3s;
        }
        .sticky-header-cta.visible {
            opacity:1;
            pointer-events:all;
        }
        .sticky-header-cta a {
            background:#10b981;
            color:#fff;
            padding:12px 25px;
            border-radius:30px;
            font-weight:700;
            text-decoration:none;
            display:inline-block;
            transition: transform 0.2s;
        }
        .sticky-header-cta a:hover {
            transform:scale(1.05);
        }
        
        @media (max-width: 768px) {
            .hero { padding:40px 20px 60px; }
            .form-container { padding:30px 20px; }
            .stats { grid-template-columns:1fr; }
            .floating-btn {
                bottom:20px;
                right:20px;
                padding:15px 25px;
                font-size:1rem;
            }
            .sticky-header-cta {
                top:65px;
                padding:10px 20px;
            }
            .sticky-header-cta a {
                padding:10px 20px;
                font-size:0.9rem;
            }
        }
    </style>
</head>
<body>

    <!-- Message Box (Alert() replacement) -->
    <div id="messageBox">
        <span id="messageText"></span>
    </div>

    <!-- Floating CTA Button -->
    <a href="#form" class="floating-btn">
        📝 अभी भरें Form
    </a>

    <!-- Sticky Header CTA (appears on scroll) -->
    <div class="sticky-header-cta" id="stickyHeaderCta">
        <a href="#form">🚀 Free Access पाएं - Form भरें</a>
    </div>

    <!-- Urgency Bar -->
    <div class="urgency-bar">
        ⚠️ Limited Spots Available: Only <strong>23 spots</strong> left this week!
        <span class="countdown">
            <span class="countdown-item" id="hours">00</span>:
            <span class="countdown-item" id="minutes">00</span>:
            <span class="countdown-item" id="seconds">00</span>
        </span>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>
                आज आप रात को क्यों नहीं सो पा रहे हैं?<br>
                <span style="color:#10b981;">The Answer Is Here.</span>
            </h1>
            <p class="subheadline">
                Discover the proven system that helped 1,247+ ordinary people escape the 9-5 trap and build a 6-figure income from home—without experience, inventory, or tech skills.
            </p>
            <a href="#form" class="cta-primary">
                🚀 Yes! Show Me How (100% Free Access)
            </a>
            
            <div class="trust-bar">
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    <span>4.9/5 Rating</span>
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z"/></svg>
                    <span>100% Secure</span>
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/></svg>
                    <span>No Credit Card</span>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:50px; padding:40px; background:#f9fafb; border-radius:16px;">
                <h3 style="font-size:1.8rem; color:#1f2937; font-weight:800; margin-bottom:20px;">
                    🤔 अभी भी सोच रहे हैं?
                </h3>
                <p style="font-size:1.2rem; color:#6b7280; margin-bottom:25px;">
                    हर रोज़ हज़ारों लोग यह मौका गंवा देते हैं। आप उनमें से मत बनिए!
                </p>
                <a href="#form" class="cta-primary">
                    ✅ नहीं, मैं यह मौका नहीं गंवाऊंगा
                </a>
            </div>
        </div>
    </section>

    <!-- Video Section -->
    <section class="video-section">
        <div class="container">
            <h2 style="font-size:2.5rem; font-weight:800; margin-bottom:20px; color:#1f2937;">
                Watch This 10-Minute Video That Changes Everything
            </h2>
            <p style="font-size:1.2rem; color:#6b7280; margin-bottom:40px;">
                (Warning: This video will make you question everything you thought you knew about building wealth)
            </p>
            <div class="video-wrapper">
                <!-- Replace with your YouTube embed -->
                <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
            
            <div class="live-counter">
                <div class="live-dot"></div>
                <strong>🎯 Limited Time Offer:</strong> अगले 24 घंटे में form भरने वाले पहले 50 लोगों को Exclusive Bonus Training मिलेगी!
            </div>
            
            <div style="text-align:center; margin-top:30px;">
                <a href="#form" class->
                    📝 हाँ! मुझे यह चाहिए - Form भरें
                </a>
            </div>
        </div>
    </section>

    <!-- Social Proof -->
    <section class="social-proof">
        <div class="container">
            <div style="background:linear-gradient(135deg, #fbbf24, #f59e0b); color:#fff; padding:30px; border-radius:16px; text-align:center; margin-bottom:50px; box-shadow:0 10px 40px rgba(251,191,36,0.3);">
                <h3 style="font-size:2rem; font-weight:800; margin-bottom:15px;">⚡ सीमित समय का ऑफर!</h3>
                <p style="font-size:1.3rem; font-weight:600;">आज ही फॉर्म भरें और पाएं <span style="background:#fff; color:#f59e0b; padding:5px 15px; border-radius:8px;">₹9,999 का FREE Training Bonus!</span></p>
                <a href="#form" class="cta-primary" style="margin-top:20px; display:inline-block;">
                    💎 अभी Claim करें
                </a>
            </div>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number">1,247+</div>
                    <div class="stat-label">Success Stories</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">₹2.4Cr+</div>
                    <div class="stat-label">Total Earnings Generated</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">4.9/5</div>
                    <div class="stat-label">Average Rating</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">23</div>
                    <div class="stat-label">Spots Left This Week</div>
                </div>
            </div>

            <h2 style="font-size:2.5rem; font-weight:800; text-align:center; margin-bottom:50px; color:#1f2937;">
                Real People. Real Results. Real Freedom.
            </h2>

            <div class="testimonials">
                <div class="testimonial">
                    <div class="testimonial-text">
                        "6 महीने पहले मैं एक frustrated housewife थी। आज मैं महीने का ₹1.2 लाख कमा रही हूँ और अपने बच्चों के साथ ज्यादा समय बिता रही हूँ।"
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">P</div>
                        <div>
                            <div class="testimonial-name">Priya Sharma</div>
                            <div class="testimonial-role">Mumbai, Homemaker → Entrepreneur</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial">
                    <div class="testimonial-text">
                        "I was stuck in a dead-end job earning ₹35k/month. Today I earn ₹2.8 lakhs/month and work from anywhere. This system changed my life."
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">R</div>
                        <div>
                            <div class="testimonial-name">Rahul Verma</div>
                            <div class="testimonial-role">Delhi, Ex-IT Professional</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial">
                    <div class="testimonial-text">
                        "मैंने अपनी नौकरी छोड़ दी और 90 दिनों में अपनी पुरानी salary से ज्यादा कमाना शुरू कर दिया। Best decision ever!"
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">A</div>
                        <div>
                            <div class="testimonial-name">Anjali Gupta</div>
                            <div class="testimonial-role">Bangalore, Former Sales Manager</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:50px; background:#fff3cd; padding:30px; border-radius:12px; border:2px dashed #ffc107;">
                <p style="font-size:1.3rem; color:#856404; font-weight:700; margin-bottom:15px;">
                    ⏰ याद रखें: जो लोग आज action लेते हैं, वही कल successful होते हैं!
                </p>
                <a href="#form" class="cta-primary">
                    🎯 अपना Future Secure करें - Form भरें
                </a>
            </div>
        </div>
    </section>

    <!-- Pain Points -->
    <section class="pain-section">
        <div class="container">
            <h2>Are You Tired Of...</h2>
            <div class="pain-grid">
                <div class="pain-card">
                    <h3>❌ Living Paycheck to Paycheck</h3>
                    <p>Working 50+ hours a week but still struggling to save money or enjoy life.</p>
                </div>
                <div class="pain-card">
                    <h3>❌ Missing Your Kids Growing Up</h3>
                    <p>Sacrificing precious family time for a job that doesn't appreciate you.</p>
                </div>
                <div class="pain-card">
                    <h3>❌ Feeling Stuck & Hopeless</h3>
                    <p>Knowing you're capable of more but not knowing how to break free.</p>
                </div>
                <div class="pain-card">
                    <h3>❌ Watching Others Succeed</h3>
                    <p>Seeing people with less experience living the life you dream of.</p>
                </div>
                <div class="pain-card">
                    <h3>❌ Fearing the Future</h3>
                    <p>Worrying about job security, retirement, and your family's financial future.</p>
                </div>
                <div class="pain-card">
                    <h3>❌ Trying "Opportunities" That Fail</h3>
                    <p>Wasting time and money on schemes that promise big but deliver nothing.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Solution -->
    <section class="solution-section">
        <div class="container">
            <h2>Imagine If You Could...</h2>
            <p class="solution-intro">
                What if there was a proven system that gave you the freedom, income, and lifestyle you deserve—without the usual risks?
            </p>

            <div class="benefits">
                <div class="benefit-card">
                    <div class="benefit-icon">💰</div>
                    <h3>Build a 6-Figure Income</h3>
                    <p>Earn ₹1 lakh to ₹5 lakhs per month using a simple, duplicatable system that works for anyone.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">🏠</div>
                    <h3>Work From Anywhere</h3>
                    <p>No office, no commute, no boss. Work from home, a café, or the beach—your choice.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">⏰</div>
                    <h3>Own Your Time</h3>
                    <p>Spend more time with family, pursue hobbies, travel—live life on your terms.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">📈</div>
                    <h3>Leverage & Scale</h3>
                    <p>Build once, earn forever. Create passive income streams that grow while you sleep.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">🎓</div>
                    <h3>World-Class Training</h3>
                    <p>Get step-by-step guidance, proven scripts, and ongoing support from experts.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">🤝</div>
                    <h3>Join a Winning Community</h3>
                    <p>Connect with 1,000+ like-minded achievers who support and inspire each other.</p>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:60px; padding:50px 30px; background:linear-gradient(135deg, #e0f2fe, #bae6fd); border-radius:16px;">
                <h3 style="font-size:2.2rem; color:#0c4a6e; font-weight:800; margin-bottom:20px;">
                    💡 सिर्फ 2 मिनट में अपनी ज़िन्दगी बदलें!
                </h3>
                <p style="font-size:1.2rem; color:#075985; margin-bottom:30px;">
                    बस नीचे दिया हुआ form भरें और देखें कैसे हज़ारों लोगों ने अपने सपने पूरे किए।
                </p>
                <a href="#form" class="cta-primary" style="font-size:1.3rem; padding:20px 50px;">
                    👇 अभी Form भरें - 100% FREE
                </a>
            </div>
        </div>
    </section>

    <!-- Form Section -->
    <section class="form-section" id="form">
        <div class="form-container">
            <div class="form-header">
                <h2>अपनी जानकारी भरें</h2>
                <p>Get Instant Access to Life-Changing Opportunity</p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="ref" value="<?php echo htmlspecialchars($ref ?? ''); ?>">

                <div class="form-group">
                    <label for="name">आपका नाम (Your Name) *</label>
                    <input type="text" id="name" name="name" required placeholder="उदाहरण: राहुल शर्मा" autocomplete="name">
                </div>

                <div class="form-group">
                    <label for="phone">व्हाट्सएप नंबर (WhatsApp Number) *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="उदाहरण: 9876543210" pattern="[0-9]{10}" inputmode="numeric">
                    <span class="hint">10 अंकों का नंबर डालें (बिना +91 के)</span>
                </div>

                <div class="form-group">
                    <label for="email">ईमेल आईडी (Email ID) *</label>
                    <input type="email" id="email" name="email" required placeholder="उदाहरण: name@example.com" autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="city">आपका शहर (Your City) *</label>
                    <input type="text" id="city" name="city" required placeholder="उदाहरण: मुंबई" autocomplete="address-level2">
                </div>

                <button type="submit" class="submit-btn">
                    💬 WhatsApp पर जुड़ें (Connect on WhatsApp)
                </button>

                <div class="form-footer">
                    🔒 आपकी जानकारी 100% सुरक्षित है। No spam, ever.<br>
                    By continuing you agree to our <a href="/terms.php" style="color:#667eea;">Terms</a> and <a href="/privacy.php" style="color:#667eea;">Privacy</a>.
                </div>
            </form>
        </div>
    </section>

    <script>
        // Store PHP error message (if any)
        const phpErrorMessage = "<?php echo json_encode($error_message); ?>";
        
        // Countdown Timer (24 hours from now)
        function startCountdown() {
            // Check if timer end time is stored, if not, set it 24 hours from now
            let endTime = localStorage.getItem('countdownEndTime');
            if (!endTime) {
                endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
                localStorage.setItem('countdownEndTime', endTime);
            } else {
                endTime = parseInt(endTime);
            }
            
            const hoursEl = document.getElementById('hours');
            const minutesEl = document.getElementById('minutes');
            const secondsEl = document.getElementById('seconds');

            if (!hoursEl || !minutesEl || !secondsEl) return; // Exit if elements not found

            const interval = setInterval(() => {
                const now = new Date().getTime();
                let distance = endTime - now;

                if (distance < 0) {
                    clearInterval(interval);
                    distance = 0; // Set to 0 if time ran out
                    // Optional: Reset timer for 24 hours again if needed
                    // endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
                    // localStorage.setItem('countdownEndTime', endTime);
                    // distance = endTime - now;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                hoursEl.textContent = String(hours).padStart(2, '0');
                minutesEl.textContent = String(minutes).padStart(2, '0');
                secondsEl.textContent = String(seconds).padStart(2, '0');

            }, 1000);
        }
        startCountdown();

        // --- MESSAGE BOX FUNCTION (Replaces alert()) ---
        function showMessageBox(message, isSuccess = false) {
            const messageBox = document.getElementById('messageBox');
            const messageText = document.getElementById('messageText');

            if (!messageBox || !messageText) return;

            messageText.textContent = message;
            messageBox.style.backgroundColor = isSuccess ? '#10b981' : '#ef4444'; // Green for success, Red for error
            messageBox.style.display = 'block';
            
            // Force reflow for fade-in effect
            void messageBox.offsetWidth; 
            messageBox.style.opacity = '1';

            // Auto-hide after 4 seconds
            setTimeout(() => {
                messageBox.style.opacity = '0';
                setTimeout(() => {
                    messageBox.style.display = 'none';
                }, 300); // Wait for fade out
            }, 4000);
        }
        
        // Display PHP Error on load if present
        if (phpErrorMessage && phpErrorMessage !== 'null') {
            try {
                // Decode JSON string passed from PHP
                const message = JSON.parse(phpErrorMessage);
                showMessageBox(message);
            } catch (e) {
                // Fallback in case JSON encoding failed
                showMessageBox("सबमिशन के दौरान एक अज्ञात त्रुटि हुई। (An unknown error occurred during submission.)");
            }
        }


        // Form validation (Updated to use showMessageBox)
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();
            
            // Simple check for empty fields (though 'required' attribute should handle this)
            if (!document.getElementById('name').value || !phone || !email || !document.getElementById('city').value) {
                e.preventDefault();
                showMessageBox('कृपया सभी आवश्यक फ़ील्ड भरें। (Please fill out all required fields)');
                return false;
            }

            // Validate phone number (10 digits)
            if (!/^[0-9]{10}$/.test(phone)) {
                e.preventDefault();
                showMessageBox('कृपया 10 अंकों का सही व्हाट्सएप नंबर डालें। (Please enter a valid 10-digit WhatsApp number)');
                return false;
            }
            
            // Validate email
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
                e.preventDefault();
                showMessageBox('कृपया सही ईमेल आईडी डालें। (Please enter a valid email address)');
                return false;
            }
            
            // Client-side validation successful, form will now attempt POST request
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Sticky header CTA on scroll
        window.addEventListener('scroll', function() {
            const stickyHeaderCta = document.getElementById('stickyHeaderCta');
            const formSection = document.getElementById('form');
            if (!stickyHeaderCta || !formSection) return;

            const scrollPosition = window.scrollY;
            const formPosition = formSection.offsetTop;
            
            // Show sticky CTA after scrolling 300px but hide when form is visible
            if (scrollPosition > 300 && scrollPosition < formPosition - 200) {
                stickyHeaderCta.classList.add('visible');
            } else {
                stickyHeaderCta.classList.remove('visible');
            }
        });
    </script>
</body>
</html>
