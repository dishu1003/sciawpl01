<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_login();

header('Content-Type: application/json');

$lead_id = $_POST['lead_id'] ?? 0;
$template_type = $_POST['template_type'] ?? 'whatsapp';
$message = $_POST['message'] ?? '';

try {
    // Fetch lead
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        throw new Exception('Lead not found');
    }
    
    // Replace placeholders
    $message = str_replace('[Name]', $lead['name'], $message);
    $message = str_replace('[Phone]', $lead['phone'], $message);
    $message = str_replace('[Email]', $lead['email'], $message);
    
    if ($template_type === 'whatsapp') {
        // WhatsApp API integration (example)
        $whatsapp_url = "https://api.whatsapp.com/send?phone=" . urlencode($lead['phone']) . "&text=" . urlencode($message);
        
        // Log activity
        log_activity($lead_id, $_SESSION['user_id'], 'WhatsApp Template Sent', substr($message, 0, 100));
        
        echo json_encode([
            'success' => true,
            'redirect_url' => $whatsapp_url
        ]);
    } elseif ($template_type === 'email') {
        // Email sending (using PHP mail or SMTP)
        $subject = "Follow-up from " . SITE_NAME;
        $headers = "From: " . SITE_NAME . " <noreply@yoursite.com>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $sent = mail($lead['email'], $subject, nl2br($message), $headers);
        
        if ($sent) {
            log_activity($lead_id, $_SESSION['user_id'], 'Email Template Sent', substr($message, 0, 100));
            echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
        } else {
            throw new Exception('Failed to send email');
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>