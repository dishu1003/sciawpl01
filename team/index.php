<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_login();

$user = get_current_user();
$pdo = get_pdo_connection();

// Fetch leads assigned to this user
$stmt = $pdo->prepare("SELECT * FROM leads WHERE assigned_to = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$leads = $stmt->fetchAll();

// Group leads by status for the Kanban board
$grouped_leads = [];
foreach ($leads as $lead) {
    $grouped_leads[$lead['status']][] = $lead;
}

// Define the columns for our Kanban board
$kanban_columns = ['New', 'Contacted', 'Plan Shown', 'Follow-up', 'Joined', 'Not Interested'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leads Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* Kanban Board Styles */
        .kanban-board {
            display: flex;
            gap: 20px;
            padding: 20px;
            overflow-x: auto;
            background-color: #f4f7f9;
            border-radius: 10px;
            min-height: 70vh;
        }
        .kanban-column {
            flex: 1;
            min-width: 280px;
            background-color: #e9eef2;
            border-radius: 8px;
            padding: 15px;
        }
        .kanban-column h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #d9dfe5;
            display: flex;
            justify-content: space-between;
        }
        .kanban-column .count {
            background-color: #d0d9e0;
            color: #555;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        .kanban-cards {
            min-height: 50px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .kanban-card {
            background-color: #fff;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: grab;
            border-left: 4px solid #3498db;
        }
        .kanban-card:active {
            cursor: grabbing;
        }
        .kanban-card h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
        }
        .kanban-card p {
            margin: 0;
            font-size: 13px;
            color: #777;
        }
        .kanban-card .source {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            background-color: #f0f0f0;
            color: #555;
            display: inline-block;
            margin-top: 10px;
        }
        .dragging {
            opacity: 0.5;
        }
        .drag-over {
            background-color: #d9e2e9;
        }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?></h1>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="dashboard">
        <aside class="sidebar">
            <ul>
                <li><a href="/team/" class="active">My Leads</a></li>
                <li><a href="/team/scripts.php">Scripts Library</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <h2>My Leads Board</h2>
            <div class="kanban-board">
                <?php foreach ($kanban_columns as $status): ?>
                    <div class="kanban-column" data-status="<?php echo $status; ?>">
                        <h3>
                            <?php echo $status; ?>
                            <span class="count"><?php echo count($grouped_leads[$status] ?? []); ?></span>
                        </h3>
                        <div class="kanban-cards">
                            <?php if (isset($grouped_leads[$status])): ?>
                                <?php foreach ($grouped_leads[$status] as $lead): ?>
                                    <div class="kanban-card" draggable="true" data-lead-id="<?php echo $lead['id']; ?>">
                                        <h4><?php echo htmlspecialchars($lead['name']); ?></h4>
                                        <p><?php echo date('d M Y', strtotime($lead['created_at'])); ?></p>
                                        <span class="source"><?php echo htmlspecialchars($lead['source'] ?? 'organic'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <script>
        const cards = document.querySelectorAll('.kanban-card');
        const columns = document.querySelectorAll('.kanban-column');

        cards.forEach(card => {
            card.addEventListener('dragstart', () => {
                card.classList.add('dragging');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
            });
        });

        columns.forEach(column => {
            column.addEventListener('dragover', e => {
                e.preventDefault();
                column.classList.add('drag-over');
                const afterElement = getDragAfterElement(column, e.clientY);
                const draggable = document.querySelector('.dragging');
                const cardContainer = column.querySelector('.kanban-cards');
                if (afterElement == null) {
                    cardContainer.appendChild(draggable);
                } else {
                    cardContainer.insertBefore(draggable, afterElement);
                }
            });

            column.addEventListener('dragleave', () => {
                 column.classList.remove('drag-over');
            });

            column.addEventListener('drop', e => {
                e.preventDefault();
                column.classList.remove('drag-over');
                const draggable = document.querySelector('.dragging');
                const leadId = draggable.dataset.leadId;
                const newStatus = column.dataset.status;

                // --- Update Status via API ---
                updateLeadStatus(leadId, newStatus);
            });
        });

        function getDragAfterElement(column, y) {
            const draggableElements = [...column.querySelectorAll('.kanban-card:not(.dragging)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        function updateLeadStatus(leadId, newStatus) {
            fetch('/api/update-lead-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lead_id: leadId,
                    status: newStatus,
                    user_id: <?php echo $user['id']; ?> // For security check
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error: Could not update lead status.');
                    // Optional: Move card back to original column on failure
                    window.location.reload();
                }
                // Update count
                updateAllCounts();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                window.location.reload();
            });
        }

        function updateAllCounts() {
            columns.forEach(column => {
                const countSpan = column.querySelector('.count');
                const cardCount = column.querySelectorAll('.kanban-card').length;
                countSpan.textContent = cardCount;
            });
        }
    </script>
</body>
</html>
