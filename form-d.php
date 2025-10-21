<?php
require_once __DIR__ . '/includes/init.php';

// Ensure user completed previous forms
if (empty($_SESSION['lead_id'])) {
    $qs = !empty($_SESSION['ref']) ? '?ref='.urlencode($_SESSION['ref']) : '';
    header('Location: /index.php' . $qs);
    exit('Invalid access. Please complete previous forms first.');
}

$lead_id = $_SESSION['lead_id'];
$ref = $_SESSION['ref'] ?? ($_GET['ref'] ?? '');

// Fetch lead data to pre-fill phone
$stmt = $pdo->prepare("SELECT phone FROM leads WHERE id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 4: Book Your Strategy Call</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <section class="form-section">
        <div class="container">
            <div class="progress-bar">
                <div class="step active">1</div>
                <div class="step active">2</div>
                <div class="step active">3</div>
                <div class="step active">4</div>
            </div>
            
            <h2 class="lang-en">🎉 Final Step: Book Your FREE Strategy Call</h2>
            <h2 class="lang-hi" style="display:none;">🎉 अंतिम चरण: अपनी मुफ्त रणनीति कॉल बुक करें</h2>
            
            <p class="lang-en" style="text-align:center; margin-bottom:30px;">
                Congratulations! You're one step away from transforming your business. 
                Let's schedule a personalized strategy call with our expert.
            </p>
            <p class="lang-hi" style="display:none; text-align:center; margin-bottom:30px;">
                बधाई हो! आप अपने व्यवसाय को बदलने से एक कदम दूर हैं। 
                आइए हमारे विशेषज्ञ के साथ एक व्यक्तिगत रणनीति कॉल शेड्यूल करें।
            </p>
            
            <form id="form-d" class="lead-form" method="POST" action="/forms/submit_d.php">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="ref_id" value="<?php echo h($ref); ?>">
                
                <div class="form-group">
                    <label class="lang-en">Preferred Date for Call *</label>
                    <label class="lang-hi" style="display:none;">कॉल के लिए पसंदीदा तारीख *</label>
                    <input type="date" name="preferred_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="lang-en">Preferred Time Slot *</label>
                    <label class="lang-hi" style="display:none;">पसंदीदा समय स्लॉट *</label>
                    <select name="preferred_time" required>
                        <option value="">Select time...</option>
                        <option value="10am-12pm">10:00 AM - 12:00 PM</option>
                        <option value="12pm-2pm">12:00 PM - 2:00 PM</option>
                        <option value="2pm-4pm">2:00 PM - 4:00 PM</option>
                        <option value="4pm-6pm">4:00 PM - 6:00 PM</option>
                        <option value="6pm-8pm">6:00 PM - 8:00 PM</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="lang-en">Preferred Call Platform *</label>
                    <label class="lang-hi" style="display:none;">पसंदीदा कॉल प्लेटफॉर्म *</label>
                    <select name="call_platform" required>
                        <option value="">Select platform...</option>
                        <option value="phone">Phone Call</option>
                        <option value="whatsapp">WhatsApp Video</option>
                        <option value="zoom">Zoom</option>
                        <option value="google_meet">Google Meet</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="lang-en">WhatsApp Number (for call link) *</label>
                    <label class="lang-hi" style="display:none;">व्हाट्सएप नंबर (कॉल लिंक के लिए) *</label>
                    <input type="tel" name="whatsapp_number" required pattern="[0-9]{10}" 
                           value="<?php echo h($lead['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="lang-en">Alternative Date (Optional)</label>
                    <label class="lang-hi" style="display:none;">वैकल्पिक तारीख (वैकल्पिक)</label>
                    <input type="date" name="alternative_date" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="lang-en">Any specific topics you want to discuss? *</label>
                    <label class="lang-hi" style="display:none;">कोई विशिष्ट विषय जिस पर आप चर्चा करना चाहते हैं? *</label>
                    <textarea name="discussion_topics" required rows="3" 
                              placeholder="E.g., Scaling my business, Lead generation, Sales strategies..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display:flex; align-items:center;">
                        <input type="checkbox" name="terms_agreed" value="1" required style="width:auto; margin-right:10px;">
                        <span class="lang-en">I agree to receive call/WhatsApp messages for the scheduled strategy session *</span>
                        <span class="lang-hi" style="display:none;">मैं निर्धारित रणनीति सत्र के लिए कॉल/व्हाट्सएप संदेश प्राप्त करने के लिए सहमत हूं *</span>
                    </label>
                </div>
                
                <button type="submit" class="submit-btn" style="background:#27ae60;">
                    <span class="lang-en">🎯 Confirm My Strategy Call</span>
                    <span class="lang-hi" style="display:none;">🎯 मेरी रणनीति कॉल की पुष्टि करें</span>
                </button>
            </form>
            
            <div style="text-align:center; margin-top:30px; padding:20px; background:#f8f9fa; border-radius:10px;">
                <h3 class="lang-en">✅ What to Expect on the Call:</h3>
                <h3 class="lang-hi" style="display:none;">✅ कॉल पर क्या उम्मीद करें:</h3>
                <ul style="text-align:left; max-width:500px; margin:20px auto;">
                    <li class="lang-en">Personalized business growth strategy</li>
                    <li class="lang-hi" style="display:none;">व्यक्तिगत व्यवसाय विकास रणनीति</li>
                    <li class="lang-en">Clear roadmap to achieve your revenue goals</li>
                    <li class="lang-hi" style="display:none;">अपने राजस्व लक्ष्यों को प्राप्त करने के लिए स्पष्ट रोडमैप</li>
                    <li class="lang-en">Answers to all your questions</li>
                    <li class="lang-hi" style="display:none;">आपके सभी सवालों के जवाब</li>
                    <li class="lang-en">Exclusive bonus for call attendees</li>
                    <li class="lang-hi" style="display:none;">कॉल में भाग लेने वालों के लिए विशेष बोनस</li>
                </ul>
            </div>
        </div>
    </section>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>