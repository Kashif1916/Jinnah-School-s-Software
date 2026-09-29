<?php
/**
 * Printable Student Cards Grid - Exact ID Card Design Replica
 * School Finance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

// Access check for allowed roles
if (!is_master() && !is_finance() && !is_admission() && !is_teacher()) {
    header('Location: ../login.php');
    exit();
}

// Fetch Filters
$search_id = sanitize_input($_GET['search_id'] ?? '');
$search_name = sanitize_input($_GET['search_name'] ?? '');
$search_father_name = sanitize_input($_GET['search_father_name'] ?? '');
$search_b_form = sanitize_input($_GET['search_b_form'] ?? '');
$search_classes = isset($_GET['search_classes']) && is_array($_GET['search_classes']) ? $_GET['search_classes'] : [];
$search_sections = isset($_GET['search_sections']) && is_array($_GET['search_sections']) ? $_GET['search_sections'] : [];
$search_reasons = isset($_GET['search_reasons']) && is_array($_GET['search_reasons']) ? $_GET['search_reasons'] : [];

$where_clauses = ["1=1"];
$params = [];
$param_types = '';

if (!empty($search_id)) {
    $where_clauses[] = "id = ?";
    $params[] = intval($search_id);
    $param_types .= 'i';
}
if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $param_types .= 's';
}
if (!empty($search_father_name)) {
    $where_clauses[] = "father_name LIKE ?";
    $params[] = '%' . $search_father_name . '%';
    $param_types .= 's';
}
if (!empty($search_b_form)) {
    $where_clauses[] = "b_form LIKE ?";
    $params[] = '%' . $search_b_form . '%';
    $param_types .= 's';
}
if (!empty($search_classes)) {
    $placeholders = implode(',', array_fill(0, count($search_classes), '?'));
    $where_clauses[] = "class IN ($placeholders)";
    foreach ($search_classes as $c) {
        $params[] = sanitize_input($c);
        $param_types .= 's';
    }
}
if (!empty($search_sections)) {
    $placeholders = implode(',', array_fill(0, count($search_sections), '?'));
    $where_clauses[] = "section IN ($placeholders)";
    foreach ($search_sections as $s) {
        $params[] = sanitize_input($s);
        $param_types .= 's';
    }
}
if (!empty($search_reasons)) {
    $placeholders = implode(',', array_fill(0, count($search_reasons), '?'));
    $where_clauses[] = "concession_reason IN ($placeholders)";
    foreach ($search_reasons as $r) {
        $params[] = sanitize_input($r);
        $param_types .= 's';
    }
}

$where_sql = implode(" AND ", $where_clauses);
$query = "SELECT * FROM students WHERE $where_sql ORDER BY class ASC, section ASC, name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Cards Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap');

        body {
            background-color: #e2e8f0;
            font-family: 'Montserrat', 'Segoe UI', sans-serif;
            color: #1a202c;
        }

        /* Cards Grid Section Fix */
        .cards-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            max-width: 1200px;
            margin: 15px auto;
        }

        /* Card Container - CR80 Standard PVC Dimensions with Perfect Space Distribution */
        .id-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            page-break-inside: avoid;
            width: 5.4cm !important;   /* Standard CR80 Width */
            height: 8.56cm !important; /* Standard CR80 Height */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Spreads content evenly to eliminate white space */
            overflow: hidden;
            box-sizing: border-box;
            position: relative;
        }

        /* Curved Header Styling */
        .card-header-wrapper {
            position: relative;
            background: #ffffff;
            flex-shrink: 0;
        }

        .card-header-bg {
            background: linear-gradient(135deg, #0b4128 0%, #115736 100%);
            padding: 8px 6px 16px 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }

        /* Gold Wave Accent Below Header */
        .card-header-wrapper::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 12px;
            background: #ffffff;
            clip-path: ellipse(75% 100% at 50% 100%);
            border-top: 2px solid #d4af37;
        }

        /* EXACT CIRCLE LOGO FIX */
        .card-header-bg img, 
        .card-header-bg .report-logo {
            width: 32px !important;
            height: 32px !important;
            object-fit: cover !important;
            border-radius: 50% !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            mix-blend-mode: normal !important;
            flex-shrink: 0;
        }

        .header-titles {
            color: #ffffff;
        }

        .header-titles h2 {
            font-size: 13px;
            font-weight: 900;
            margin: 0;
            letter-spacing: 0.5px;
            line-height: 1;
            color: #ffffff;
        }

        .header-titles h3 {
            font-size: 6px;
            font-weight: 700;
            margin: 2px 0 0 0;
            color: #f1f5f9;
            letter-spacing: 0.2px;
            line-height: 1.1;
        }

        /* Card Main Body - Fills all middle area without dead whitespace */
        .card-body-container {
            padding: 4px 10px 2px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-evenly; /* Even spacing across body */
            flex-grow: 1;
            z-index: 2;
        }

        /* Photo Frame Box - Proportionate to fill space nicely */
        .photo-frame-box {
            width: 78px;
            height: 94px;
            border: 2px dashed #115736;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #94a3b8;
            font-size: 9px;
            font-weight: 600;
            margin: 2px 0;
        }

        .photo-frame-box i {
            font-size: 22px;
            color: #115736;
            margin-bottom: 2px;
            opacity: 0.4;
        }

        /* Student Name & ID */
        .student-name {
            font-size: 13px;
            font-weight: 900;
            color: #0b4128;
            text-transform: uppercase;
            text-align: center;
            margin-top: 2px;
            margin-bottom: 0px;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        .student-id-tag {
            font-size: 8.5px;
            font-weight: 700;
            color: #334155;
            text-align: center;
            margin-bottom: 4px;
        }

        /* Details List Table */
        .details-grid-table {
            width: 100%;
            font-size: 7.5px;
            line-height: 1.25;
            margin-top: 2px;
        }

        .details-grid-table td {
            padding: 1px 0;
            vertical-align: top;
        }

        .details-grid-table .col-label {
            font-weight: 800;
            color: #1e293b;
            width: 70px;
            text-transform: UPPERCASE;
        }

        .details-grid-table .col-val {
            font-weight: 700;
            color: #0f172a;
        }

        /* Footer Barcode & Registrar Signature */
        .card-footer-strip {
            background: #e2e8f0;
            padding: 4px 10px 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1px solid #cbd5e1;
            flex-shrink: 0;
        }

        .barcode-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .barcode-lines {
            height: 16px;
            width: 80px;
            background: repeating-linear-gradient(
                90deg,
                #000 0,
                #000 1.5px,
                #fff 1.5px,
                #fff 3px,
                #000 3px,
                #000 5px,
                #fff 5px,
                #fff 6px
            );
        }

        .barcode-subtext {
            font-size: 7px;
            font-weight: 800;
            color: #1e293b;
            margin-top: 1px;
        }

        .registrar-sign-section {
            text-align: center;
        }

        .sign-image {
            width: 42px;
            height: auto;
            display: block;
            margin: 0 auto -2px auto;
        }   
        .registrar-text {
            font-size: 7px;
            font-weight: 900;
            color: #0b4128;
            border-top: 1px solid #64748b;
            padding-top: 1px;
            text-transform: UPPERCASE;
            letter-spacing: 0.3px;
        }

        /* Controls Bar (No Print) */
        .no-print-bar {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            padding: 12px 24px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .cards-grid {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 0.4cm !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.4cm !important;
                justify-content: flex-start !important;
            }
            .id-card {
                width: 5.4cm !important;
                height: 8.56cm !important;
                box-shadow: none !important;
                border: 1px solid #000000 !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0 text-success fw-bold"><i class="fas fa-id-card me-2"></i>Student ID Cards (Total: <?php echo count($students); ?>)</h5>
            <small class="text-muted">Exact CR80 PVC Size (5.4 cm x 8.56 cm) with Balanced Full-Height Layout.</small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-success fw-bold px-4">
                <i class="fas fa-print me-1"></i> Print Student Cards
            </button>
            <button onclick="window.close()" class="btn btn-secondary px-3">Close</button>
        </div>
    </div>

    <div class="container-fluid py-3">
        <?php if (count($students) > 0): ?>
            <div class="cards-grid">
                <?php foreach ($students as $s): ?>
                    <div class="id-card">
                        <!-- Curved Green Header with Gold Accent -->
                        <div class="card-header-wrapper">
                            <div class="card-header-bg">
                                <?php echo render_system_logo('report-logo'); ?>
                                <div class="header-titles">
                                    <h2>JINNAH</h2>
                                    <h3>HIGH SCHOOL & INTER COLLEGE KHUSHAB</h3>
                                    <h3>PH NO: 0309-6684856</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Main Body Content -->
                        <div class="card-body-container">
                            <!-- Photo Frame Box -->
                            <div class="photo-frame-box">
                                <i class="fas fa-user"></i>
                                Photo
                            </div>

                            <!-- Student Title Info -->
                            <div class="student-name"><?php echo htmlspecialchars($s['name']); ?></div>
                            <div class="student-id-tag">STUDENT ID: <?php echo htmlspecialchars($s['id']); ?></div>

                            <!-- Student Info Grid Table -->
                            <table class="details-grid-table">
                                <tr>
                                    <td class="col-label">FATHER'S NAME:</td>
                                    <td class="col-val"><?php echo htmlspecialchars($s['father_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="col-label">CLASS & SECTION:</td>
                                    <td class="col-val"><?php echo htmlspecialchars($s['class']); ?> - <?php echo htmlspecialchars($s['section']); ?></td>
                                </tr>
                                
                                <tr>
                                    <td class="col-label">CONTACT NO:</td>
                                    <td class="col-val"><?php echo !empty($s['contact_number']) ? htmlspecialchars($s['contact_number']) : (!empty($s['whatsapp_number']) ? htmlspecialchars($s['whatsapp_number']) : '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="col-label">ADDRESS:</td>
                                    <td class="col-val" style="word-break: break-word;">
                                        <?php echo !empty($s['address']) ? htmlspecialchars($s['address']) : '-'; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Footer Barcode & Registrar Sign -->
                        <div class="card-footer-strip">
                            <div class="barcode-section">
                                <div class="barcode-lines"></div>
                                <span class="barcode-subtext">REG-<?php echo sprintf('%05d', $s['id']); ?></span>
                            </div>
                            <div class="registrar-sign-section">
                                <img src="../images/signature.png" alt="Abu Bakar" class="sign-image">
                                <div class="registrar-text">Principal</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center my-5" style="max-width: 500px; margin: 0 auto;">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                No student records found for the selected filter criteria.
            </div>
        <?php endif; ?>
    </div>

</body>
</html>