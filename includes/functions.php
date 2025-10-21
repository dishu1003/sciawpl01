<?php
require_once __DIR__ . '/../config/database.php';

function log_activity($lead_id, $user_id, $action, $details = '') {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("INSERT INTO logs (lead_id, user_id, action, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$lead_id, $user_id, $action, $details]);
}

function assign_lead($lead_id, $user_id) {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
    $stmt->execute([$user_id, $lead_id]);
    log_activity($lead_id, $_SESSION['user_id'] ?? null, 'Lead Assigned', "Assigned to user ID: $user_id");
}

function update_lead_score($lead_id, $score) {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE leads SET lead_score = ? WHERE id = ?");
    $stmt->execute([$score, $lead_id]);
    log_activity($lead_id, $_SESSION['user_id'] ?? null, 'Score Updated', "New score: $score");
}

function update_lead_status($lead_id, $status) {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
    $stmt->execute([$status, $lead_id]);
    log_activity($lead_id, $_SESSION['user_id'] ?? null, 'Status Updated', "New status: $status");
}

function add_lead_note($lead_id, $note) {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE leads SET notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] ', ?) WHERE id = ?");
    $stmt->execute([$note, $lead_id]);
    log_activity($lead_id, $_SESSION['user_id'] ?? null, 'Note Added', $note);
}

function send_webhook($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function calculate_lead_score($lead) {
    // Simple scoring logic - customize as needed
    $score = 0;
    
    if ($lead['current_step'] >= 3) $score += 30;
    if ($lead['current_step'] == 4) $score += 20;
    if ($lead['form_b_submitted_at']) $score += 20;
    if ($lead['form_c_submitted_at']) $score += 30;
    
    if ($score >= 70) return 'HOT';
    if ($score >= 40) return 'WARM';
    return 'COLD';
}
?>