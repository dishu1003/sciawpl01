<?php
/**
 * Webhook API Endpoint
 * Handles incoming webhooks from external services (Abacus AI, etc.)
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers for API
SecurityHeaders::setAll();
header('Content-Type: application/json');

// Rate limiting - 30 requests per minute, block for 5 minutes
$rateLimiter = new RateLimiter($pdo, 'webhook_api');
if (!$rateLimiter->check(30, 60, 300)) {
    http_response_code(429);
    Logger::security('Webhook API rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Verify webhook secret
$headers = getallheaders();
$signature = $headers['X-Webhook-Signature'] ?? '';

if (empty($signature) || $signature !== WEBHOOK_SECRET) {
    http_response_code(401);
    Logger::security('Unauthorized webhook attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'signature_provided' => !empty($signature),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get webhook payload
$raw_payload = file_get_contents('php://input');
$payload = json_decode($raw_payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    Logger::warning('Invalid JSON in webhook payload', [
        'error' => json_last_error_msg(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$event_type = Sanitizer::string($payload['event_type'] ?? '');
$lead_id = (int)($payload['lead_id'] ?? 0);

// Validate required fields
if (empty($event_type)) {
    http_response_code(400);
    Logger::warning('Missing event_type in webhook', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'payload' => $payload
    ]);
    echo json_encode(['error' => 'Missing event_type']);
    exit;
}

try {
    switch ($event_type) {
        case 'send_followup':
            if ($lead_id <= 0) {
                throw new Exception('Invalid lead_id');
            }

            // Fetch lead data
            $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            $lead = $stmt->fetch();

            if ($lead) {
                // Log follow-up action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_logs (lead_id, user_id, action, notes, created_at)
                    VALUES (?, NULL, ?, ?, NOW())
                ");
                $logStmt->execute([
                    $lead_id,
                    'Automated Follow-up Sent',
                    'Triggered by Abacus AI via webhook'
                ]);

                Logger::info('Webhook: Follow-up sent', [
                    'lead_id' => $lead_id,
                    'lead_name' => $lead['name'],
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);

                $response = ['success' => true, 'message' => 'Follow-up sent successfully'];
            } else {
                throw new Exception('Lead not found');
            }
            break;

        case 'update_score':
            if ($lead_id <= 0) {
                throw new Exception('Invalid lead_id');
            }

            $new_score = strtoupper(Sanitizer::string($payload['score'] ?? 'COLD'));
            $valid_scores = ['HOT', 'WARM', 'COLD'];

            if (!in_array($new_score, $valid_scores)) {
                throw new Exception('Invalid score value');
            }

            $stmt = $pdo->prepare("UPDATE leads SET lead_score = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_score, $lead_id]);

            Logger::info('Webhook: Lead score updated', [
                'lead_id' => $lead_id,
                'new_score' => $new_score,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            $response = ['success' => true, 'message' => 'Score updated successfully'];
            break;

        case 'assign_lead':
            if ($lead_id <= 0) {
                throw new Exception('Invalid lead_id');
            }

            $user_id = (int)($payload['user_id'] ?? 0);

            if ($user_id <= 0) {
                throw new Exception('Invalid user_id');
            }

            // Verify user exists
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
            $userStmt->execute([$user_id]);

            if (!$userStmt->fetch()) {
                throw new Exception('User not found or inactive');
            }

            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $lead_id]);

            Logger::info('Webhook: Lead assigned', [
                'lead_id' => $lead_id,
                'user_id' => $user_id,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            $response = ['success' => true, 'message' => 'Lead assigned successfully'];
            break;

        default:
            throw new Exception('Unknown event type: ' . $event_type);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    Logger::error('Webhook processing error', [
        'event_type' => $event_type,
        'lead_id' => $lead_id,
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    echo json_encode(['error' => $e->getMessage()]);
}