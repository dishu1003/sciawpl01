<?php
// /scripts/issue_certificates.php (CLI)
require_once __DIR__ . '/../includes/init.php';
$pdo = get_pdo_connection();

// CONFIG
$threshold_percent = 80; // 80% to issue certificate
$upload_dir = __DIR__ . '/../uploads/certificates/';
if (!is_dir($upload_dir)) mkdir($upload_dir,0755,true);

// Get courses list
$courses = $pdo->query("SELECT * FROM courses")->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as $course) {
    $course_id = $course['id'];

    // get lessons for course
    $lessons = $pdo->prepare("
        SELECT l.id, l.material_id
        FROM lessons l
        JOIN modules m ON l.module_id = m.id
        WHERE m.course_id = ?
    ");
    $lessons->execute([$course_id]);
    $lessRows = $lessons->fetchAll(PDO::FETCH_ASSOC);
    $totalLessons = count($lessRows);
    if ($totalLessons == 0) continue;

    // for each team user check progress for lessons' attached materials (if material_id NULL count as 100%? We'll require material)
    $users = $pdo->query("SELECT id, username, email, name FROM users WHERE role='team'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        $user_id = $user['id'];

        // calculate completed lessons for this user
        $completedCount = 0;
        $interactionsCount = 0;
        foreach ($lessRows as $ln) {
            if (empty($ln['material_id'])) {
                // if lesson has no material, consider it completed by default
                $completedCount++;
                $interactionsCount++;
                continue;
            }
            // get progress record for this material
            $stmt = $pdo->prepare("SELECT progress_percent FROM training_progress WHERE user_id = ? AND material_id = ?");
            $stmt->execute([$user_id, $ln['material_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['progress_percent']) >= 100) {
                $completedCount++;
            }
            $interactionsCount++;
        }

        $percent = round(($completedCount / $totalLessons) * 100, 2);

        // if threshold met and no certificate exists
        $check = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND course_id = ?");
        $check->execute([$user_id, $course_id]);
        $exists = $check->fetch();

        if ($percent >= $threshold_percent && !$exists) {
            // generate certificate
            $issuedAt = date('Y-m-d H:i:s');
            $filename = 'certificate_'.$course_id.'_'.$user_id.'_'.time().'.pdf';
            $outPath = $upload_dir . $filename;

            // fetch user & course rows
            $courseRow = $course;
            $userRow = $user;

            require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
            // generate pdf using same function as above (duplicated here for CLI)
            $pdf = new FPDF('L','mm','A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',28);
            $pdf->Cell(0,20,'',0,1);
            $pdf->Cell(0,10,'Certificate of Completion',0,1,'C');
            $pdf->SetFont('Arial','',16);
            $pdf->Cell(0,12,utf8_decode("This certificate is proudly presented to"),0,1,'C');
            $pdf->SetFont('Arial','B',24);
            $displayName = $userRow['name'] ?: $userRow['username'];
            $pdf->Cell(0,14,utf8_decode($displayName),0,1,'C');
            $pdf->SetFont('Arial','',16);
            $pdf->Cell(0,12,utf8_decode("for successfully completing the course:"),0,1,'C');
            $pdf->SetFont('Arial','B',20);
            $pdf->Cell(0,14,utf8_decode($courseRow['title']),0,1,'C');
            $pdf->SetFont('Arial','',12);
            $pdf->Cell(0,12,utf8_decode("Issued on: ".$issuedAt),0,1,'C');
            $pdf->Output('F', $outPath);

            if (file_exists($outPath)) {
                // insert DB record
                $ins = $pdo->prepare("INSERT INTO certificates (user_id, course_id, file_path, issued_at) VALUES (?, ?, ?, ?)");
                $ins->execute([$user_id, $course_id, '/uploads/certificates/'.$filename, $issuedAt]);

                // Email notify user (simple mail; replace with PHPMailer for reliability)
                if (!empty($userRow['email'])) {
                    $to = $userRow['email'];
                    $subject = "Your Certificate for " . $courseRow['title'];
                    $message = "Hi ".($userRow['name'] ?: $userRow['username']).",\n\nCongratulations! You have completed the course '".$courseRow['title']."'. Your certificate is attached.\n\nRegards,\nTeam";
                    $headers = "From: no-reply@".$_SERVER['HTTP_HOST']."\r\n";
                    // Attach file (simple base64 attach)
                    // For simplicity here we'll include download link instead of attachment:
                    $message .= "\n\nDownload: " . (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . "://" . $_SERVER['HTTP_HOST'] . "/uploads/certificates/".$filename;
                    @mail($to, $subject, $message, $headers);
                }
            }
        }
    } // end users
} // end courses
