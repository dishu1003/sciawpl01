<?php
// api/update-lead-status.php
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Get the posted data
$data = json_decode(file_get_contents('php_input'), true);

$lead_id = $data['lead_id'] ?? 0;
$new_status = $data['status'] ?? '';
$user_id = $data['user_id'] ?? 0; // The user ID from the frontend

// --- Security Check ---
// 1. Check if a user is logged in at all
require_login();

// 2. Get the currently logged-in user from the session
$session_user = get_current_user();
if (!$session_user) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed.']);
    exit;
}

// 3. Ensure the user ID from the frontend matches the logged-in user
if ($user_id !== $session_user['id']) {
    echo json_encode(['success' => false, 'message' => 'User mismatch. Security validation failed.']);
    exit;
}

// 4. Verify that the lead is actually assigned to this user before updating
try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$lead_id, $session_user['id']]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Permission denied. You do not own this lead.']);
        exit;
    }
} catch (Exception $e) {
    // Log this error
    error_log("Database error during lead ownership check: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
    exit;
}


// --- Update the Lead Status ---
try {
    // We can use the existing function from functions.php
    update_lead_status($lead_id, $new_status);

    // Respond with success
    echo json_encode(['success' => true, 'message' => 'Lead status updated.']);

} catch (Exception $e) {
    // Log this error
    error_log("Failed to update lead status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update lead status.']);
}
