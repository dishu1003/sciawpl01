<?php
/**
 * Form C Submission Handler
 * Handles investment capacity and commitment data
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    Logger::warning('Invalid request method for Form C', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    exit('Method Not Allowed');
}

// Rate limiting - 5 submissions per 5 minutes, block for 15 minutes
$rateLimiter = new RateLimiter($pdo, 'form_c_submission');
if (!$rateLimiter->check(5, 300, 900)) {
    Logger::security('Form C rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    $_SESSION['error'] = 'Too many submissions. Please try again in 15 minutes.';
    header('Location: /form-c.php');
    exit;
}

// CSRF Protection
try {
    CSRF::validateRequest();
} catch (Exception $e) {
    Logger::security('CSRF validation failed for Form C', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'error' => $e->getMessage()
    ]);
    $_SESSION['error'] = 'Security validation failed. Please try again.';
    header('Location: /form-c.php');
    exit;
}

// Check if lead_id exists in session
$lead_id = $_SESSION['lead_id'] ?? null;
if (!$lead_id) {
    Logger::warning('Form C submitted without lead_id', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'session_id' => session_id()
    ]);
    header('Location: /index.php');
    exit;
}

// Sanitize and validate input
$investment_capacity = Sanitizer::string($_POST['investment_capacity'] ?? '');
$decision_maker = Sanitizer::string($_POST['decision_maker'] ?? '');
$commitment_level = Sanitizer::string($_POST['commitment_level'] ?? '');
$time_commitment = Sanitizer::string($_POST['time_commitment'] ?? '');
$start_timeline = Sanitizer::string($_POST['start_timeline'] ?? '');
$biggest_concern = Sanitizer::string($_POST['biggest_concern'] ?? '');
$ref_id = $_SESSION['ref'] ?? ($_POST['ref_id'] ?? '');

// Validation
$validator = new Validator();
$validator->required('investment_capacity', $investment_capacity, 'Investment capacity is required');
$validator->required('decision_maker', $decision_maker, 'Decision maker information is required');
$validator->required('commitment_level', $commitment_level, 'Commitment level is required');
$validator->required('time_commitment', $time_commitment, 'Time commitment is required');
$validator->required('start_timeline', $start_timeline, 'Start timeline is required');
$validator->required('biggest_concern', $biggest_concern, 'Biggest concern is required');

if ($validator->fails()) {
    $errors = $validator->getErrors();
    Logger::warning('Form C validation failed', [
        'lead_id' => $lead_id,
        'errors' => $errors,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    $_SESSION['error'] = 'Please complete all required fields: ' . implode(', ', array_keys($errors));
    header('Location: /form-c.php');
    exit;
}

// Prepare form data
$formC = [
    'investment_capacity' => $investment_capacity,
    'decision_maker' => $decision_maker,
    'commitment_level' => $commitment_level,
    'time_commitment' => $time_commitment,
    'start_timeline' => $start_timeline,
    'biggest_concern' => $biggest_concern,
];

try {
    // Update lead with Form C data
    $stmt = $pdo->prepare("
        UPDATE leads
        SET form_c_data = :c,
            form_c_submitted_at = NOW(),
            current_step = 4,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':c' => json_encode($formC, JSON_UNESCAPED_UNICODE),
        ':id' => $lead_id
    ]);

    // Log successful submission
    Logger::info('Form C submitted successfully', [
        'lead_id' => $lead_id,
        'investment_capacity' => $investment_capacity,
        'commitment_level' => $commitment_level,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Redirect to Form D
    $qs = $ref_id ? '?ref=' . urlencode($ref_id) : '';
    header('Location: ' . SITE_URL . '/form-d.php' . $qs);
    exit;

} catch (PDOException $e) {
    Logger::error('Database error in Form C submission', [
        'lead_id' => $lead_id,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    $_SESSION['error'] = 'An error occurred. Please try again.';
    header('Location: /form-c.php');
    exit;
}