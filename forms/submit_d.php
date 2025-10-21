<?php
/**
 * Form D Submission Handler
 * Handles call scheduling and final confirmation
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Logger::warning('Invalid request method for Form D', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    exit('Method Not Allowed');
}

// Rate limiting - 5 submissions per 5 minutes, block for 15 minutes
$rateLimiter = new RateLimiter($pdo, 'form_d_submission');
if (!$rateLimiter->check(5, 300, 900)) {
    Logger::security('Form D rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    $_SESSION['error'] = 'Too many submissions. Please try again in 15 minutes.';
    header('Location: /form-d.php');
    exit;
}

// CSRF Protection
try {
    CSRF::validateRequest();
} catch (Exception $e) {
    Logger::security('CSRF validation failed for Form D', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'error' => $e->getMessage()
    ]);
    $_SESSION['error'] = 'Security validation failed. Please try again.';
    header('Location: /form-d.php');
    exit;
}

// Check if lead_id exists in session
$lead_id = $_SESSION['lead_id'] ?? null;
if (!$lead_id) {
    Logger::warning('Form D submitted without lead_id', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'session_id' => session_id()
    ]);
    header('Location: /index.php');
    exit;
}

// Sanitize and validate input
$preferred_date = Sanitizer::string($_POST['preferred_date'] ?? '');
$preferred_time = Sanitizer::string($_POST['preferred_time'] ?? '');
$call_platform = Sanitizer::string($_POST['call_platform'] ?? '');
$whatsapp_number = Sanitizer::string($_POST['whatsapp_number'] ?? '');
$alternative_date = Sanitizer::string($_POST['alternative_date'] ?? '');
$discussion_topics = Sanitizer::string($_POST['discussion_topics'] ?? '');
$terms_agreed = isset($_POST['terms_agreed']) ? 1 : 0;
$ref_id = $_SESSION['ref'] ?? ($_POST['ref_id'] ?? '');

// Validation
$validator = new Validator();
$validator->required('preferred_date', $preferred_date, 'Preferred date is required');
$validator->required('preferred_time', $preferred_time, 'Preferred time is required');
$validator->required('call_platform', $call_platform, 'Call platform is required');
$validator->required('whatsapp_number', $whatsapp_number, 'WhatsApp number is required');
$validator->phone('whatsapp_number', $whatsapp_number, 'Invalid WhatsApp number format');
$validator->required('discussion_topics', $discussion_topics, 'Discussion topics are required');

// Check terms agreement
if (!$terms_agreed) {
    Logger::warning('Form D submitted without terms agreement', [
        'lead_id' => $lead_id,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    $_SESSION['error'] = 'You must agree to the terms and conditions.';
    header('Location: /form-d.php');
    exit;
}

if ($validator->fails()) {
    $errors = $validator->getErrors();
    Logger::warning('Form D validation failed', [
        'lead_id' => $lead_id,
        'errors' => $errors,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    $_SESSION['error'] = 'Please complete all required fields: ' . implode(', ', array_keys($errors));
    header('Location: /form-d.php');
    exit;
}

// Prepare form data
$formD = [
    'preferred_date' => $preferred_date,
    'preferred_time' => $preferred_time,
    'call_platform' => $call_platform,
    'whatsapp_number' => $whatsapp_number,
    'alternative_date' => $alternative_date,
    'discussion_topics' => $discussion_topics,
    'terms_agreed' => $terms_agreed,
];

try {
    // Update lead with Form D data and mark as completed
    $stmt = $pdo->prepare("
        UPDATE leads
        SET form_d_data = :d,
            form_d_submitted_at = NOW(),
            current_step = 5,
            status = 'completed',
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':d' => json_encode($formD, JSON_UNESCAPED_UNICODE),
        ':id' => $lead_id
    ]);

    // Log successful submission
    Logger::info('Form D submitted successfully - Lead completed', [
        'lead_id' => $lead_id,
        'preferred_date' => $preferred_date,
        'preferred_time' => $preferred_time,
        'call_platform' => $call_platform,
        'whatsapp_number' => $whatsapp_number,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Clear lead_id from session as process is complete
    unset($_SESSION['lead_id']);

    // Redirect to thank you page
    $qs = $ref_id ? '?ref=' . urlencode($ref_id) : '';
    header('Location: ' . SITE_URL . '/thank-you.php' . $qs);
    exit;

} catch (PDOException $e) {
    Logger::error('Database error in Form D submission', [
        'lead_id' => $lead_id,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    $_SESSION['error'] = 'An error occurred. Please try again.';
    header('Location: /form-d.php');
    exit;
}