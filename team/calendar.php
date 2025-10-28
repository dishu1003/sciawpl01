<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();

    // fetch user
    $user_stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // fetch reminders for this user
    $stmt = $pdo->prepare("
        SELECT r.id, r.reminder_time, l.name AS lead_name, l.id AS lead_id
        FROM reminders r
        JOIN leads l ON r.lead_id = l.id
        WHERE r.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fetch leads assigned to user (for modal)
    $leads_stmt = $pdo->prepare("SELECT id, name FROM leads WHERE assigned_to = ? ORDER BY name");
    $leads_stmt->execute([$user_id]);
    $leads = $leads_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Calendar DB Error: ' . $e->getMessage());
    $reminders = [];
    $leads = [];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📅 Calendar - Team</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/jsCalendar.min.css">
    <script src="/assets/js/jsCalendar.min.js"></script>
    <style>
      /* minimal modal styling if not present in global CSS */
      .modal { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; }
      .modal .modal-content { background:#fff; padding:20px; border-radius:8px; width:90%; max-width:500px; }
      .close-button { float:right; cursor:pointer; font-size:20px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
        <main class="main-content">
            <div class="header"><h1>📅 Calendar</h1></div>

            <div id="calendar"></div>

            <!-- Modal -->
            <div id="reminderModal" class="modal" aria-hidden="true">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2>Reminder Add / Edit</h2>

                    <form id="reminderForm">
                        <input type="hidden" id="reminder_id" name="id" value="">
                        <div class="form-group">
                            <label for="reminderDate">Date:</label>
                            <input type="date" id="reminderDate" name="reminder_date" required>
                        </div>

                        <div class="form-group">
                            <label for="reminderTime">Time:</label>
                            <input type="time" id="reminderTime" name="reminder_time" required>
                        </div>

                        <div class="form-group">
                            <label for="lead">Lead:</label>
                            <select id="lead" name="lead_id" required>
                                <option value="">— Select Lead —</option>
                                <?php foreach ($leads as $lead): ?>
                                    <option value="<?php echo (int)$lead['id']; ?>"><?php echo htmlspecialchars($lead['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
                            <button type="button" id="cancelBtn">Cancel</button>
                            <button type="submit">Save Reminder</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // calendar init
    var calendar = jsCalendar.new('#calendar');
    var modal = document.getElementById('reminderModal');
    var closeButton = document.querySelector('.close-button');
    var cancelBtn = document.getElementById('cancelBtn');
    var reminderForm = document.getElementById('reminderForm');

    // sound (place file at /assets/sounds/alert.mp3)
    var reminderSound = new Audio('/assets/sounds/alert.mp3');

    // load reminders from PHP into JS array
    const reminders = <?php echo json_encode(array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'datetime' => date('Y-m-d H:i:s', strtotime($r['reminder_time'])),
            'lead' => $r['lead_name'],
            'lead_id' => (int)$r['lead_id'],
            'notified' => false
        ];
    }, $reminders)); ?>;

    // render events on calendar
    reminders.forEach(r => {
        var dateOnly = r.datetime.split(' ')[0].replace(/-/g,'/');
        calendar.addEvent({ from: dateOnly, to: dateOnly, summary: 'Follow up: ' + r.lead });
    });

    // request notification permission
    if ("Notification" in window) {
        Notification.requestPermission().then(function(permission) {
            console.log('Notification permission:', permission);
        });
    }

    // helper: open modal and fill
    function openModal(dateStr, prefill) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden','false');
        if (prefill) {
            document.getElementById('reminder_id').value = prefill.id || '';
            document.getElementById('reminderDate').value = prefill.date || dateStr;
            document.getElementById('reminderTime').value = prefill.time || '';
            document.getElementById('lead').value = prefill.lead_id || '';
        } else {
            document.getElementById('reminder_id').value = '';
            document.getElementById('reminderDate').value = dateStr;
            document.getElementById('reminderTime').value = '';
            document.getElementById('lead').value = '';
        }
    }

    // on date click -> open modal
    calendar.onDateClick(function(event, date) {
        var isoDate = date.toISOString().slice(0,10);
        openModal(isoDate, null);
    });

    closeButton.onclick = function() { modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); };
    cancelBtn.onclick = function() { modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); };
    window.onclick = function(e) { if (e.target == modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); } };

    // speech helper (Hindi)
    function playVoiceAlert(lead) {
        try {
            var text = `Dishant bhai, fifteen minutes baad follow up hai ${lead} se.`;
            var u = new SpeechSynthesisUtterance(text);
            u.lang = 'hi-IN';
            u.rate = 0.95;
            speechSynthesis.speak(u);
        } catch (ex) { console.warn('Voice not available', ex); }
    }

    // open wa.me quick (you can change to team/lead specific number logic)
    function openWhatsApp(lead, time) {
        // NOTE: This opens wa.me in new tab for user to send. Replace number logic as needed.
        var phone = ''; // if you want automatic recipient, set number here (with country code, e.g., 9198xxxxxxxx)
        var msg = `Reminder: Follow up with ${lead} at ${time}`;
        if (phone) window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
        else console.log('WhatsApp quick-open skipped (phone unset)');
    }

    // server-side email
    function sendEmailReminder(lead, time) {
        fetch('/api/send_email_reminder.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ lead: lead, time: time })
        }).catch(e=>console.warn('Email API error', e));
    }

    // AJAX save create_reminder
    reminderForm.onsubmit = function(e) {
        e.preventDefault();
        var id = document.getElementById('reminder_id').value || '';
        var date = document.getElementById('reminderDate').value;
        var time = document.getElementById('reminderTime').value;
        var lead_id = document.getElementById('lead').value;

        if (!date || !time || !lead_id) { alert('Please fill all fields'); return; }

        var formData = new FormData();
        formData.append('id', id);
        formData.append('reminder_date', date);
        formData.append('reminder_time', time);
        formData.append('lead_id', lead_id);

        fetch('/api/create_reminder.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to save reminder');
                    console.warn(data);
                }
            }).catch(err => { alert('Request failed'); console.error(err); });
    };

    // reminder checker -> every minute
    setInterval(function() {
        var now = new Date().getTime();
        reminders.forEach(function(rem) {
            if (rem.notified) return;
            var remTime = new Date(rem.datetime).getTime();
            var diff = remTime - now;
            if (diff > 0 && diff <= 15 * 60 * 1000) {
                rem.notified = true;
                try { reminderSound.play().catch(()=>{}); } catch(e){}
                // voice
                playVoiceAlert(rem.lead);
                // push
                if (Notification.permission === 'granted') {
                    new Notification('Follow-up Reminder 🚨', { body: `Follow up with ${rem.lead} at ${new Date(remTime).toLocaleTimeString()}`, icon: '/assets/icons/bell.png' });
                }
                // WhatsApp quick-open (optional)
                openWhatsApp(rem.lead, new Date(remTime).toLocaleTimeString());
                // Email
                sendEmailReminder(rem.lead, new Date(remTime).toLocaleTimeString());
                // fallback alert
                try { alert(`🚨 Reminder: Follow up with ${rem.lead} at ${new Date(remTime).toLocaleTimeString()}`); } catch(e){}
            }
        });
    }, 60 * 1000); // 1 min
});
</script>
</body>
</html>
