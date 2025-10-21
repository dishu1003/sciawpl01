<?php
require_once __DIR__ . '/includes/init.php';

// Ensure user completed Form A
if (empty($_SESSION['lead_id'])) {
    // Carry ref back to home if needed
    $qs = !empty($_SESSION['ref']) ? '?ref='.urlencode($_SESSION['ref']) : '';
    header('Location: /index.php' . $qs);
    exit('Invalid access. Please complete Form A first.');
}

$ref = $_SESSION['ref'] ?? ($_GET['ref'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Step 2: Engagement & Value</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <section class="form-section">
    <div class="container">
      <div class="progress-bar">
        <div class="step active">1</div>
        <div class="step active">2</div>
        <div class="step">3</div>
        <div class="step">4</div>
      </div>

      <h2>Step 2: Tell Us More About Your Goals</h2>

      <form id="form-b" class="lead-form" method="POST" action="/forms/submit_b.php">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="ref_id" value="<?php echo h($ref); ?>">
        <!-- We do NOT send lead_id, we rely on session -->

        <div class="form-group">
          <label>What's your monthly revenue goal? *</label>
          <select name="monthly_goal" required>
            <option value="">Select...</option>
            <option value="1-5lakh">₹1-5 Lakh</option>
            <option value="5-10lakh">₹5-10 Lakh</option>
            <option value="10-25lakh">₹10-25 Lakh</option>
            <option value="25lakh+">₹25+ Lakh</option>
          </select>
        </div>

        <div class="form-group">
          <label>How soon do you want to achieve this? *</label>
          <select name="timeline" required>
            <option value="">Select...</option>
            <option value="3months">Within 3 months</option>
            <option value="6months">Within 6 months</option>
            <option value="1year">Within 1 year</option>
          </select>
        </div>

        <div class="form-group">
          <label>What type of training are you interested in? *</label>
          <select name="training_interest" required>
            <option value="">Select...</option>
            <option value="digital_marketing">Digital Marketing</option>
            <option value="sales">Sales & Closing</option>
            <option value="business_scaling">Business Scaling</option>
            <option value="all">All of the above</option>
          </select>
        </div>

        <div class="form-group">
          <label>Have you invested in training before? *</label>
          <select name="previous_training" required>
            <option value="">Select...</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
          </select>
        </div>

        <button type="submit" class="submit-btn">Continue to Step 3 →</button>
      </form>
    </div>
  </section>
</body>
</html>