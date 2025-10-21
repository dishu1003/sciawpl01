<?php
require_once 'config/config.php';
require_once 'config/database.php';

$step = $_GET['step'] ?? 1;
$lead_id = $_GET['lead_id'] ?? 0;

// Verify lead exists
$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    die('Invalid lead ID');
}

$next_step = $step + 1;
$next_form_url = '';

switch ($next_step) {
    case 2:
        $next_form_url = '/forms/form-b.php?lead_id=' . $lead_id;
        break;
    case 3:
        $next_form_url = '/forms/form-c.php?lead_id=' . $lead_id;
        break;
    case 4:
        $next_form_url = '/forms/form-d.php?lead_id=' . $lead_id;
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Step <?php echo $step; ?> Complete</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="confirmation-page">
        <div class="container">
            <div class="success-icon">✓</div>
            <h1>Thank You, <?php echo htmlspecialchars($lead['name']); ?>!</h1>
            <p>Step <?php echo $step; ?> of 4 completed successfully.</p>
            
            <?php if ($next_step <= 4): ?>
                <div class="progress-bar">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="step <?php echo $i <= $step ? 'active' : ''; ?>"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>
                
                <p class="next-step-text">Ready for the next step?</p>
                <a href="<?php echo $next_form_url; ?>" class="cta-btn">Continue to Step <?php echo $next_step; ?> →</a>
            <?php else: ?>
                <h2>🎉 All Steps Completed!</h2>
                <p>Our team will contact you within 24 hours.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>