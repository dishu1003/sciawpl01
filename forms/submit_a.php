<?php
// forms/submit_a.php

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../includes/sanitizer.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

// Set security headers (if SecurityHeaders class exists)
if (class_exists('SecurityHeaders')) {
    SecurityHeaders::setAll();
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// PDO connection
$pdo = get_pdo_connection();

// Rate limiting (5 attempts per 15 minutes)
$rateLimiter = new RateLimiter($pdo, 'form_submission');
if (!$rateLimiter->check(5, 900, 900)) { // 5 submissions, 900s = 15 mins
    Logger::security('Rate limit exceeded for Form A', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    $_SESSION['error'] = 'Too many submissions. Please try again in 15 minutes.';
    header('Location: /index.php');
    exit;
}

// CSRF check
CSRF::validateRequest();

// Sanitize input
$full_name = Sanitizer::clean($_POST['full_name'] ?? '');
$best_email = Sanitizer::email($_POST['best_email'] ?? '');
$phone = Sanitizer::phone($_POST['phone'] ?? '');
$reason_insomnia = Sanitizer::clean($_POST['reason_insomnia'] ?? '');
$three_months_ready = Sanitizer::clean($_POST['three_months_ready'] ?? '');
$ref_id = Sanitizer::clean($_POST['ref_id'] ?? ($_SESSION['ref'] ?? ''));

// Validation
$validator = new Validator();
$validator->required('full_name', $full_name)->minLength('full_name', $full_name, 2)->maxLength('full_name', $full_name, 100);
$validator->required('best_email', $best_email)->email('best_email', $best_email);
if ($phone) $validator->phone('phone', $phone);
$validator->required('reason_insomnia', $reason_insomnia)->minLength('reason_insomnia', $reason_insomnia, 10);
$validator->required('three_months_ready', $three_months_ready)->enum('three_months_ready', $three_months_ready, ['yes','no','maybe']);

// Validation failed
if ($validator->fails()) {
    $_SESSION['error'] = $validator->getFirstError();
    $_SESSION['old_input'] = $_POST;
    Logger::warning('Form A validation failed', $validator->getErrors());
    header('Location: /index.php');
    exit;
}

// Duplicate email check
$stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
$stmt->execute([$best_email]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'This email is already registered.';
    $_SESSION['old_input'] = $_POST;
    Logger::info('Duplicate email attempt', ['email' => $best_email]);
    header('Location: /index.php');
    exit;
}

// Insert lead
try {
    $formA = [
        'full_name' => $full_name,
        'best_email' => $best_email,
        'phone' => $phone,
        'reason_insomnia' => $reason_insomnia,
        'three_months_ready' => $three_months_ready,
    ];

    $stmt = $pdo->prepare("
        INSERT INTO leads (name, email, phone, ref_id, form_a_data, form_a_submitted_at, current_step, created_at, updated_at)
        VALUES (:name, :email, :phone, :ref_id, :form_a, NOW(), 2, NOW(), NOW())
    ");

    $stmt->execute([
        ':name' => $full_name,
        ':email' => $best_email,
        ':phone' => $phone,
        ':ref_id' => $ref_id ?: null,
        ':form_a' => json_encode($formA, JSON_UNESCAPED_UNICODE)
    ]);

    $lead_id = $pdo->lastInsertId();
    $_SESSION['lead_id'] = $lead_id;

    Logger::info('Form A submitted successfully', [
        'lead_id' => $lead_id,
        'email' => $best_email,
        'ref_id' => $ref_id
    ]);

    unset($_SESSION['old_input']);
    $qs = $ref_id ? '?ref=' . urlencode($ref_id) : '';
    header('Location: ' . SITE_URL . '/form-b.php' . $qs);
    exit;

} catch (PDOException $e) {
    Logger::error('Database error in Form A submission', ['error' => $e->getMessage()]);
    $_SESSION['error'] = 'An error occurred. Please try again.';
    $_SESSION['old_input'] = $_POST;
    header('Location: /index.php');
    exit;
}
