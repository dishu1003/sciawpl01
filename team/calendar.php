<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT r.*, l.name as lead_name FROM reminders r JOIN leads l ON r.lead_id = l.id WHERE r.user_id = ?");
    $stmt->execute([$user_id]);
    $reminders = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle database error
    $reminders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Team Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/jsCalendar.min.css">
    <script src="/assets/js/jsCalendar.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
        <main class="main-content">
            <div class="header">
                <h1>Calendar</h1>
            </div>
            <div id="calendar"></div>
            <div id="reminderModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2>Add Reminder</h2>
                    <form id="reminderForm">
                        <input type="hidden" id="reminderDate" name="reminder_date">
                        <div class="form-group">
                            <label for="lead">Lead:</label>
                            <select id="lead" name="lead_id" required>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, name FROM leads WHERE assigned_to = ?");
                                $stmt->execute([$user_id]);
                                $leads = $stmt->fetchAll();
                                foreach ($leads as $lead) {
                                    echo "<option value=\"{$lead['id']}\">{$lead['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reminderTime">Time:</label>
                            <input type="time" id="reminderTime" name="reminder_time" required>
                        </div>
                        <button type="submit">Save</button>
                    </form>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var calendar = jsCalendar.new('#calendar');
                    var modal = document.getElementById('reminderModal');
                    var closeButton = document.querySelector('.close-button');
                    var reminderForm = document.getElementById('reminderForm');
                    var reminderDateInput = document.getElementById('reminderDate');

                    <?php foreach ($reminders as $reminder): ?>
                        calendar.addEvent({
                            from: '<?php echo date('Y/m/d', strtotime($reminder['reminder_time'])); ?>',
                            to: '<?php echo date('Y/m/d', strtotime($reminder['reminder_time'])); ?>',
                            summary: 'Follow up with <?php echo htmlspecialchars($reminder['lead_name']); ?>'
                        });
                    <?php endforeach; ?>

                    calendar.onDateClick(function(event, date) {
                        reminderDateInput.value = date.toISOString().slice(0, 10);
                        modal.style.display = 'block';
                    });

                    closeButton.onclick = function() {
                        modal.style.display = 'none';
                    };

                    window.onclick = function(event) {
                        if (event.target == modal) {
                            modal.style.display = 'none';
                        }
                    };

                    reminderForm.onsubmit = function(event) {
                        event.preventDefault();
                        var formData = new FormData(reminderForm);

                        fetch('/api/create_reminder.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                modal.style.display = 'none';
                                location.reload();
                            } else {
                                alert('Failed to create reminder.');
                            }
                        });
                    };
                });
            </script>
        </main>
    </div>
</body>
</html>
