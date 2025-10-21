<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_admin();

// Fetch all leads
$stmt = $pdo->query("
    SELECT l.*, u.name as assigned_to_name 
    FROM leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    ORDER BY l.created_at DESC
");
$leads = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'ID',
    'Name',
    'Email',
    'Phone',
    'Current Step',
    'Lead Score',
    'Status',
    'Assigned To',
    'Ref ID',
    'Form A Submitted',
    'Form B Submitted',
    'Form C Submitted',
    'Form D Submitted',
    'Created At',
    'Updated At'
]);

// Add data rows
foreach ($leads as $lead) {
    fputcsv($output, [
        $lead['id'],
        $lead['name'],
        $lead['email'],
        $lead['phone'],
        $lead['current_step'],
        $lead['lead_score'],
        $lead['status'],
        $lead['assigned_to_name'] ?: 'Unassigned',
        $lead['ref_id'] ?: 'Direct',
        $lead['form_a_submitted_at'] ?: 'No',
        $lead['form_b_submitted_at'] ?: 'No',
        $lead['form_c_submitted_at'] ?: 'No',
        $lead['form_d_submitted_at'] ?: 'No',
        $lead['created_at'],
        $lead['updated_at']
    ]);
}

fclose($output);
exit;
?>