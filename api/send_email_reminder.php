<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$lead = $raw['lead'] ?? '';
$time = $raw['time'] ?? '';

if (!$lead || !$time) {
    echo json_encode(['success'=>false]); exit;
}

// change to your admin/team email
$to = 'youremail@example.com';
$subject = "Reminder: Follow up with $lead";
$message = "Automated reminder:\n\nFollow up with: $lead\nScheduled at: $time\n\n- from your Reminder System";
$headers = "From: reminders@" . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . "\r\n";

$mail_sent = mail($to, $subject, $message, $headers);

echo json_encode(['success' => (bool)$mail_sent]);
