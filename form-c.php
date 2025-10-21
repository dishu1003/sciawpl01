<?php
require_once __DIR__ . '/includes/init.php';

// Ensure user completed previous forms
if (empty($_SESSION['lead_id'])) {
    $qs = !empty($_SESSION['ref']) ? '?ref='.urlencode($_SESSION['ref']) : '';
    header('Location: /index.php' . $qs);
    exit('Invalid access. Please complete previous forms first.');
}

$ref = $_SESSION['ref'] ?? ($_GET['ref'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 3: Commitment & Qualification</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <section class="form-section">
        <div class="container">
            <div class="progress-bar">
                <div class="step active">1</div>
                <div class="step active">2</div>
                <div class="step active">3</div>
                <div class="step">4</div>
            </div>

            <h2 class="lang-en">Step 3: Let's Qualify Your Commitment</h2>
            <h2 class="lang-hi" style="display:none;">चरण 3: आइए आपकी प्रतिबद्धता को योग्य बनाएं</h2>

            <form id="form-c" class="lead-form" method="POST" action="/forms/submit_c.php">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="ref_id" value="<?php echo h($ref); ?>">

                <div class="form-group">
                    <label class="lang-en">What's your investment capacity for training? *</label>
                    <label class="lang-hi" style="display:none;">प्रशिक्षण के लिए आपकी निवेश क्षमता क्या है? *</label>
                    <select name="investment_capacity" required>
                        <option value="">Select...</option>
                        <option value="under_25k">Under ₹25,000</option>
                        <option value="25k-50k">₹25,000 - ₹50,000</option>
                        <option value="50k-1lakh">₹50,000 - ₹1,00,000</option>
                        <option value="1lakh+">₹1,00,000+</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lang-en">Are you the decision maker? *</label>
                    <label class="lang-hi" style="display:none;">क्या आप निर्णय लेने वाले हैं? *</label>
                    <select name="decision_maker" required>
                        <option value="">Select...</option>
                        <option value="yes">Yes, I make my own decisions / हां, मैं अपने निर्णय खुद लेता हूं</option>
                        <option value="need_spouse">Need to discuss with spouse / पति/पत्नी से चर्चा करनी होगी</option>
                        <option value="need_partner">Need to discuss with business partner / व्यावसायिक साझेदार से चर्चा करनी होगी</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lang-en">How committed are you to making this work? *</label>
                    <label class="lang-hi" style="display:none;">आप इसे काम करने के लिए कितने प्रतिबद्ध हैं? *</label>
                    <select name="commitment_level" required>
                        <option value="">Select...</option>
                        <option value="very_serious">Very serious - I'll do whatever it takes / बहुत गंभीर</option>
                        <option value="serious">Serious - Ready to invest time & money / गंभीर</option>
                        <option value="exploring">Just exploring options / बस विकल्प तलाश रहा हूं</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lang-en">How many hours per week can you dedicate? *</label>
                    <label class="lang-hi" style="display:none;">आप प्रति सप्ताह कितने घंटे समर्पित कर सकते हैं? *</label>
                    <select name="time_commitment" required>
                        <option value="">Select...</option>
                        <option value="5-10hrs">5-10 hours</option>
                        <option value="10-20hrs">10-20 hours</option>
                        <option value="20+hrs">20+ hours (Full-time)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lang-en">When do you want to start? *</label>
                    <label class="lang-hi" style="display:none;">आप कब शुरू करना चाहते हैं? *</label>
                    <select name="start_timeline" required>
                        <option value="">Select...</option>
                        <option value="immediately">Immediately / तुरंत</option>
                        <option value="within_week">Within a week / एक सप्ताह के भीतर</option>
                        <option value="within_month">Within a month / एक महीने के भीतर</option>
                        <option value="just_researching">Just researching / बस रिसर्च कर रहा हूं</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="lang-en">What's your biggest concern about joining? *</label>
                    <label class="lang-hi" style="display:none;">शामिल होने के बारे में आपकी सबसे बड़ी चिंता क्या है? *</label>
                    <textarea name="biggest_concern" required rows="3" placeholder="Be honest..."></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <span class="lang-en">Continue to Final Step →</span>
                    <span class="lang-hi" style="display:none;">अंतिम चरण पर जाएं →</span>
                </button>
            </form>
        </div>
    </section>

    <script src="/assets/js/main.js"></script>
</body>
</html>