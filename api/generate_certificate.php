<?php
// /api/generate_certificate.php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php'; // optional if used via cron, then secure differently
// require_admin(); // if only admin should run

// check called by cron or admin (for web-trigger allow admin)
$pdo = get_pdo_connection();

function generate_pdf_certificate($userRow, $courseRow, $issuedAt, $outPath) {
    // using FPDF
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

    $pdf = new FPDF('L','mm','A4');
    $pdf->AddPage();
    // background color / simple stylings
    $pdf->SetFont('Arial','B',28);
    $pdf->Cell(0,20,'',0,1); // spacing
    $pdf->Cell(0,10,utf8_decode('Certificate of Completion'),0,1,'C');
    $pdf->SetFont('Arial','',16);
    $pdf->Cell(0,12,utf8_decode("This certificate is proudly presented to"),0,1,'C');
    $pdf->SetFont('Arial','B',24);
    $pdf->Cell(0,14,utf8_decode($userRow['name'] ?? $userRow['username']),0,1,'C');
    $pdf->SetFont('Arial','',16);
    $pdf->Cell(0,12,utf8_decode("for successfully completing the course:"),0,1,'C');
    $pdf->SetFont('Arial','B',20);
    $pdf->Cell(0,14,utf8_decode($courseRow['title']),0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,12,utf8_decode("Issued on: ".$issuedAt),0,1,'C');

    // signature placeholder
    $pdf->SetY(-50);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,utf8_decode('Authorized by: '.$courseRow['created_by'] ?? 'Admin'),0,1,'L');
    $pdf->Output('F', $outPath);
    return file_exists($outPath);
}
