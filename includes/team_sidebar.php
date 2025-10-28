<nav class="sidebar">
    <div class="user-profile">
        <div class="user-avatar">
            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
        <div class="user-role" data-en="Direct Seller" data-hi="डायरेक्ट सेलर">डायरेक्ट सेलर</div>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="/team/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/team/lead-management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'lead-management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span data-en="My Leads" data-hi="मेरे लीड्स">मेरे लीड्स</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/team/add-lead.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add-lead.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span data-en="Add Lead" data-hi="लीड जोड़ें">लीड जोड़ें</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/team/my-referrals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-referrals.php' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i>
                <span data-en="My Referrals" data-hi="मेरे रेफरल">मेरे रेफरल</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/team/calendar.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span data-en="Calendar" data-hi="कैलेंडर">कैलेंडर</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span data-en="Logout" data-hi="लॉग आउट">लॉग आउट</span>
            </a>
        </li>
    </ul>
</nav>
