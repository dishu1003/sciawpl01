<nav class="dashboard-nav">
    <div class="nav-header">
        <h1>
            <span class="emoji">🎯</span>
            <span data-en="Direct Selling" data-hi="डायरेक्ट सेलिंग">डायरेक्ट सेलिंग</span>
        </h1>
    </div>
    <div class="user-info">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
        </div>
        <div class="user-details">
            <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="role" data-en="Administrator" data-hi="व्यवस्थापक">व्यवस्थापक</div>
        </div>
    </div>
    <div class="nav-links">
        <a href="/admin/" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
        </a>
        <a href="/admin/leads.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span data-en="Lead Management" data-hi="लीड प्रबंधन">लीड प्रबंधन</span>
        </a>
        <a href="/admin/team.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'team.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-friends"></i>
            <span data-en="Team Management" data-hi="टीम प्रबंधन">टीम प्रबंधन</span>
        </a>
        <a href="/admin/calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span data-en="Calendar" data-hi="कैलेंडर">कैलेंडर</span>
        </a>
        <a href="/logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span data-en="Logout" data-hi="लॉग आउट">लॉग आउट</span>
        </a>
    </div>
</nav>
