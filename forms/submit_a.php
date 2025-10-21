<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    exit('Method Not Allowed'); 
}

// Rate limiting - 5 attempts per 5 minutes
$rateLimiter = new RateLimiter($pdo, 'form_submission');
if (!$rateLimiter->check(5, 300, 900)) {
    Logger::security('Rate limit exceeded for form submission', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    $_SESSION['error'] = 'Too many submissions. Please try again in 15 minutes.';
    header('Location: /index.php'); 
    exit;
}

// CSRF Protection
CSRF::validateRequest();

// Initialize validator
$validator = new Validator();

// Get and sanitize input
$full_name = Sanitizer::clean($_POST['full_name'] ?? '');
$best_email = Sanitizer::email($_POST['best_email'] ?? '');
$phone = Sanitizer::phone($_POST['phone'] ?? '');
$reason_insomnia = Sanitizer::clean($_POST['reason_insomnia'] ?? '');
$three_months_ready = Sanitizer::clean($_POST['three_months_ready'] ?? '');
$ref_id = Sanitizer::clean($_POST['ref_id'] ?? ($_SESSION['ref'] ?? ''));

// Validate inputs
$validator->required('full_name', $full_name);
$validator->minLength('full_name', $full_name, 2);
$validator->maxLength('full_name', $full_name, 100);

$validator->required('best_email', $best_email);
$validator->email('best_email', $best_email);

if (!empty($phone)) {
    $validator->phone('phone', $phone);
}

$validator->required('reason_insomnia', $reason_insomnia);
$validator->minLength('reason_insomnia', $reason_insomnia, 10);

$validator->required('three_months_ready', $three_months_ready);
$validator->enum('three_months_ready', $three_months_ready, ['yes', 'no', 'maybe']);

// Check validation
if ($validator->fails()) {
    $_SESSION['error'] = $validator->getFirstError();
    $_SESSION['old_input'] = $_POST;
    Logger::warning('Form A validation failed', $validator->getErrors());
    header('Location: /index.php'); 
    exit;
}

// Check for duplicate email
try {
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
    $stmt->execute([$best_email]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This email is already registered.';
        $_SESSION['old_input'] = $_POST;
        Logger::info('Duplicate email submission attempt', ['email' => $best_email]);
        header('Location: /index.php'); 
        exit;
    }

    // Prepare form data
    $formA = [
        'full_name' => $full_name,
        'best_email' => $best_email,
        'phone' => $phone,
        'reason_insomnia' => $reason_insomnia,
        'three_months_ready' => $three_months_ready,
    ];

    // Insert lead
    $stmt = $pdo->prepare("
        INSERT INTO leads (name, email, phone, ref_id, form_a_data, form_a_submitted_at, current_step, created_at, updated_at)
        VALUES (:name, :email, :phone, :ref_id, :form_a, NOW(), 2, NOW(), NOW())
    ");
    
    $stmt->execute([
        ':name' => $full_name,
        ':email' => $best_email,
        ':phone' => $phone,
        ':ref_id' => $ref_id ?: null,
        ':form_a' => json_encode($formA, JSON_UNESCAPED_UNICODE),
    ]);

    $lead_id = $pdo->lastInsertId();
    $_SESSION['lead_id'] = $lead_id;

    // Log activity
    Logger::info('Form A submitted successfully', [
        'lead_id' => $lead_id,
        'email' => $best_email,
        'ref_id' => $ref_id
    ]);

    // Clear old input on success
    unset($_SESSION['old_input']);

    // Redirect to Form B
    $qs = $ref_id ? '?ref=' . urlencode($ref_id) : '';
    header('Location: ' . SITE_URL . '/form-b.php' . $qs);
    exit;

} catch (PDOException $e) {
    Logger::error('Database error in Form A submission', [
        'error' => $e->getMessage(),
        'email' => $best_email
    ]);
    
    $_SESSION['error'] = 'An error occurred. Please try again.';
    $_SESSION['old_input'] = $_POST;
    header('Location: /index.php'); 
    exit;
}
?>
