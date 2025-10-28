<?php
require_once __DIR__ . '/../includes/init.php';

// This script should be run by a cron job every minute.

try {
    $pdo = get_pdo_connection();

    // Get all reminders that are due and haven't been sent yet
    $stmt = $pdo->prepare("
        SELECT r.*, l.name as lead_name, l.phone as lead_phone, u.full_name as team_member_name, u.phone as team_member_phone
        FROM reminders r
        JOIN leads l ON r.lead_id = l.id
        JOIN users u ON r.user_id = u.id
        WHERE r.reminder_time <= NOW() AND r.is_sent = 0
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll();

    foreach ($reminders as $reminder) {
        // Send reminder to team member
        send_whatsapp_message(
            $reminder['team_member_phone'],
            "Hi {$reminder['team_member_name']}, this is a reminder to follow up with {$reminder['lead_name']}."
        );

        // Send message to lead
        send_whatsapp_message(
            $reminder['lead_phone'],
            "Hi {$reminder['lead_name']}, this is a reminder about your follow-up with {$reminder['team_member_name']}. You can contact them at {$reminder['team_member_phone']}."
        );

        // Mark reminder as sent
        $update_stmt = $pdo->prepare("UPDATE reminders SET is_sent = 1 WHERE id = ?");
        $update_stmt->execute([$reminder['id']]);
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Error sending reminders: " . $e->getMessage());
}

function send_whatsapp_message($to, $message) {
    // In a real application, you would integrate with a WhatsApp API provider like Twilio.
    // For now, we'll just log the message to a file.
    $log_message = "[" . date('Y-m-d H:i:s') . "] Sending WhatsApp message to {$to}: {$message}\n";
    file_put_contents(__DIR__ . '/../logs/whatsapp_messages.log', $log_message, FILE_APPEND);
}
