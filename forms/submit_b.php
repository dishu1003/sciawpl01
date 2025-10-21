<?php
/**
 * Form B Submission Handler
 * Handles business goals and training interest data
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Logger::warning('Invalid request method for Form B', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    exit('Method Not Allowed');
}

// Rate limiting - 5 submissions per 5 minutes, block for 15 minutes
$rateLimiter = new RateLimiter($pdo, 'form_b_submission');
if (!$rateLimiter->check(5, 300, 900)) {
    Logger::security('Form B rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    $_SESSION['error'] = 'Too many submissions. Please try again in 15 minutes.';
    header('Location: /form-b.php');
    exit;
}

// CSRF Protection
try {
    CSRF::validateRequest();
} catch (Exception $e) {
    Logger::security('CSRF validation failed for Form B', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'error' => $e->getMessage()
    ]);
    $_SESSION['error'] = 'Security validation failed. Please try again.';
    header('Location: /form-b.php');
    exit;
}

// Check if lead_id exists in session
$lead_id = $_SESSION['lead_id'] ?? null;
if (!$lead_id) {
    Logger::warning('Form B submitted without lead_id', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'session_id' => session_id()
    ]);
    header('Location: /index.php');
    exit;
}

// Sanitize and validate input
$monthly_goal = Sanitizer::string($_POST['monthly_goal'] ?? '');
$timeline = Sanitizer::string($_POST['timeline'] ?? '');
$training_interest = Sanitizer::string($_POST['training_interest'] ?? '');
$previous_training = Sanitizer::string($_POST['previous_training'] ?? '');
$ref_id = $_SESSION['ref'] ?? ($_POST['ref_id'] ?? '');

// Validation
$validator = new Validator();
$validator->required('monthly_goal', $monthly_goal, 'Monthly goal is required');
$validator->required('timeline', $timeline, 'Timeline is required');
$validator->required('training_interest', $training_interest, 'Training interest is required');
$validator->required('previous_training', $previous_training, 'Previous training information is required');

if ($validator->fails()) {
    $errors = $validator->getErrors();
    Logger::warning('Form B validation failed', [
        'lead_id' => $lead_id,
        'errors' => $errors,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    $_SESSION['error'] = 'Please complete all required fields: ' . implode(', ', array_keys($errors));
    header('Location: /form-b.php');
    exit;
}

// Prepare form data
$formB = [
    'monthly_goal' => $monthly_goal,
    'timeline' => $timeline,
    'training_interest' => $training_interest,
    'previous_training' => $previous_training,
];

try {
    // Update lead with Form B data
    $stmt = $pdo->prepare("
        UPDATE leads
        SET form_b_data = :b,
            form_b_submitted_at = NOW(),
            current_step = 3,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':b' => json_encode($formB, JSON_UNESCAPED_UNICODE),
        ':id' => $lead_id
    ]);

    // Log successful submission
    Logger::info('Form B submitted successfully', [
        'lead_id' => $lead_id,
        'monthly_goal' => $monthly_goal,
        'timeline' => $timeline,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Redirect to Form C
    $qs = $ref_id ? '?ref=' . urlencode($ref_id) : '';
    header('Location: ' . SITE_URL . '/form-c.php' . $qs);
    exit;

} catch (PDOException $e) {
    Logger::error('Database error in Form B submission', [
        'lead_id' => $lead_id,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    $_SESSION['error'] = 'An error occurred. Please try again.';
    header('Location: /form-b.php');
    exit;
}