<?php
/**
 * Master Dashboard
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master();

// Get Statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'];
$paid_students = $conn->query("SELECT COUNT(DISTINCT fr.student_id) as count FROM fee_records fr JOIN students s ON s.id = fr.student_id WHERE fr.status = 'paid' AND s.status = 'active'")->fetch_assoc()['count'] ?? 0;
$remaining_students = max(0, $total_students - $paid_students);
$paid_percentage = $total_students > 0 ? round(($paid_students / $total_students) * 100) : 0;
$remaining_percentage = 100 - $paid_percentage;
$total_unpaid = $conn->query("SELECT SUM(amount) as total FROM fee_records WHERE status = 'unpaid'")->fetch_assoc()['total'] ?? 0;

// NEW QUERIES: Fetch count for Section B (Boys) and Section G (Girls) for active students
$total_boys = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'B' AND status = 'active'")->fetch_assoc()['count'] ?? 0;
$total_girls = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'G' AND status = 'active'")->fetch_assoc()['count'] ?? 0;

$today_collection = get_daily_collection(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper dashboard-shell">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Dashboard</h2>
                        <span>Principal Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?> 
                        <small>(Principal)</small>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn active">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn ">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                        <a href="users.php" class="module-nav-btn">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                    </div>
                </div>

                <div class="dashboard-stage">
                    <section class="stage-panel stage-panel--hero">
                        <span class="dashboard-kicker"><i class="fas fa-school"></i> Principal Dashboard</span>
                        <h3>Control the school finance system from one place.</h3>
                        <p>Track students, manage fees, review pending payments, and keep promotion work moving with quick access to every major module.</p>
                        <div class="hero-row">
                            <span class="hero-tag"><i class="fas fa-shield-alt"></i> Principal access only</span>
                            <a href="backup.php" class="hero-tag" style="text-decoration:none; color:inherit;">
                                <i class="fas fa-database"></i> Backup System
                            </a>
                        </div>
                    </section>

                    <aside class="stage-panel">
                        <div class="dashboard-nav-header">
                            <h4>Payment Graph</h4>
                            <p>Paid vs remaining active students.</p>
                        </div>
                        <div class="metric-card" style="padding: 18px;">
                            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                <div style="width: 140px; height: 140px; border-radius: 50%; background: conic-gradient(#1f5f46 0% <?php echo $paid_percentage; ?>%, #d8e2dc <?php echo $paid_percentage; ?>% 100%); position: relative; flex-shrink: 0;">
                                    <div style="position: absolute; inset: 16px; border-radius: 50%; background: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                        <strong style="font-size: 24px; margin: 0; color: #13211a;"><?php echo $paid_percentage; ?>%</strong>
                                        <small style="margin: 0; color: #6c7a73; font-size: 11px;">Paid</small>
                                    </div>
                                </div>
                                <div style="flex: 1; min-width: 180px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 13px; color: #5b6962;">
                                        <span><i class="fas fa-check-circle" style="color: #1f5f46;"></i> Paid Students</span>
                                        <strong style="color: #13211a;"><?php echo $paid_students; ?></strong>
                                    </div>
                                    <div style="height: 9px; background: #e7efea; border-radius: 999px; overflow: hidden; margin-bottom: 12px;">
                                        <div style="height: 100%; width: <?php echo $paid_percentage; ?>%; background: linear-gradient(90deg, #163325 0%, #1f5f46 100%);"></div>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 13px; color: #5b6962;">
                                        <span><i class="fas fa-hourglass-half" style="color: #90a49b;"></i> Remaining Students</span>
                                        <strong style="color: #13211a;"><?php echo $remaining_students; ?></strong>
                                    </div>
                                    <div style="height: 9px; background: #e7efea; border-radius: 999px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $remaining_percentage; ?>%; background: #c4d3cc;"></div>
                                    </div>
                                </div>
                            </div>
                            <small style="display: block; margin-top: 12px; color: #6c7a73; font-size: 12px;">
                                Total active students: <?php echo $total_students; ?>
                            </small>
                        </div>
                    </aside>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: #e3f1ea;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_students; ?></h3>
            <p>Active Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
            <i class="fas fa-mars"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_boys; ?></h3>
            <p>Boys (Sec B)</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #e3f1ea; color: #1f5f46;">
            <i class="fas fa-venus"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_girls; ?></h3>
            <p>Girls (Sec G)</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #edf4f0;">
            <i class="fas fa-circle-exclamation"></i>
        </div>
        <div class="stat-content">
    <h3><?php echo str_replace('.00', '', format_currency($total_unpaid)); ?></h3>
    <p>Total Unpaid</p>
</div>
    </div>

    
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #f0f4ef;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo format_currency($today_collection); ?></h3>
            <p>Today's Collection</p>
        </div>
    </div>
</div>
               
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>