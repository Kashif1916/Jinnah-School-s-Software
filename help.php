<?php
/**
 * Help & About Developer Page
 * School Finance Management System
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/helpers.php';

require_login(); // Ensure user is logged in
$current_role = get_user_role();
$current_username = get_username();

// Define back url depending on role
$back_url = 'index.php';
if ($current_role === 'master') {
    $back_url = 'master/dashboard.php';
} elseif ($current_role === 'finance') {
    $back_url = 'finance/dashboard.php';
} elseif ($current_role === 'admission') {
    $back_url = 'admission/add_student.php';
} elseif ($current_role === 'teacher') {
    $back_url = 'teacher/defaulter_list.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .help-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .dev-card {
            background: linear-gradient(135deg, #1f5f46 0%, #10161b 100%);
            color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(31, 95, 70, 0.25);
            transition: transform 0.3s ease;
            position: relative;
        }
        .dev-card:hover {
            transform: translateY(-5px);
        }
        .dev-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .contact-btn {
            background: white;
            color: #1f5f46;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .contact-btn:hover {
            background: #e9ecef;
            color: #163325;
            transform: scale(1.03);
        }
        /* Specific styling for LinkedIn button */
        .linkedin-btn {
            background: #0077b5;
            color: white;
        }
        .linkedin-btn:hover {
            background: #005a87;
            color: white;
        }
        .system-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .avatar-circle {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            border: 3px solid rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <?php echo render_system_logo('topbar-logo'); ?>
                    <div class="panel-brand">
                        <h2>Help & Support</h2>
                        <span>Developer Information</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($current_username); ?> 
                        <small>(<?php echo ucfirst($current_role); ?>)</small>
                    </span>
                    <a href="<?php echo $back_url; ?>" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <div class="content py-4">
                <div class="help-container">
                    
                    <!-- Main Developer Contact Card -->
                    <div class="dev-card p-5 mb-4">
                        <div class="row align-items-center g-4">
                            <div class="col-md-3 text-center d-flex justify-content-center">
                                <div class="avatar-circle">
                                    <i class="fas fa-code"></i>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <span class="dev-badge mb-2"><i class="fas fa-user-shield"></i> Official Software Developer</span>
                                <h2 class="mb-1 text-white fw-bold">Kashif Javed</h2>
                                <p class="text-white-50 mb-3" style="font-size: 1.1rem;">Full Stack Developer & Systems Architect</p>
                                
                                <p class="mb-4" style="line-height: 1.7; color: rgba(255,255,255,0.9);">
                                    If you encounter any difficulties operating the software or using any feature, please feel free to reach out via the contact numbers or email provided below. The developer is always available to assist with system customization or technical troubleshooting.
                                </p>
                                
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="tel:03180711280" class="contact-btn">
                                        <i class="fas fa-phone-alt"></i> 03180711280
                                    </a>
                                    <a href="mailto:kashifjoiya1916@gmail.com" class="contact-btn">
                                        <i class="fas fa-envelope"></i> kashifjoiya1916@gmail.com
                                    </a>
                                    <a href="https://www.linkedin.com/in/kashif-joiya-003a55282/" target="_blank" class="contact-btn linkedin-btn">
                                        <i class="fab fa-linkedin"></i> LinkedIn Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- About Us and System Info Card -->
                    <div class="system-info-card p-4">
                        <h4 class="mb-4 text-dark"><i class="fas fa-info-circle text-success me-2"></i>System Information</h4>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success"><i class="fas fa-cogs me-2"></i>System Highlights</h6>
                                <p class="text-muted small mb-0" style="line-height: 1.6;">
                                    <strong>Jinnah School Finance Management System (SFMS)</strong> is a professional school financial transaction system engineered to ensure secure transactions, maintain automated annual fee records, and generate dynamic reports.
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success"><i class="fas fa-lock me-2"></i>Security & Standards</h6>
                                <p class="text-muted small mb-0" style="line-height: 1.6;">
                                    The system is built with secure session management, SQL injection protection, dynamic DB alterations, and standard role-based access control to keep the school's financial records entirely safe and protected.
                                </p>
                            </div>
                        </div>
                        <hr class="my-4">
                        <div class="text-center text-muted small">
                            <p class="mb-0">&copy; <?php echo date('Y'); ?> Jinnah School. Developed with &hearts; by Kashif Javed.</p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>