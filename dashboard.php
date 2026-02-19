  <?php
  require_once('/home/gmpsvasy/public_html/config.php');
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        // Set Saudi Arabia timezone for completion_date
        date_default_timezone_set('Asia/Riyadh');
        
        $allowedStatuses = ['Pending', 'In Progress', 'Done'];
        if (!in_array($_POST['status'], $allowedStatuses)) {
            throw new Exception("Invalid status value");
        }

        // Check if completion_date column exists
        $columnCheck = $pdo->query("SHOW COLUMNS FROM service_requests LIKE 'completion_date'")->fetch();
        
        if ($columnCheck) {
            $stmt = $pdo->prepare("UPDATE service_requests SET status = ?, completion_date = ? WHERE id = ?");
            $completionDate = ($_POST['status'] === 'Done') ? date('Y-m-d H:i:s') : NULL;
            $stmt->execute([$_POST['status'], $completionDate, $_POST['id']]);
        } else {
            // Fallback if column doesn't exist
            $stmt = $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['id']]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM service_requests WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Search functionality - now handled client-side
$search = $_GET['search'] ?? '';

// Get requests grouped by status (removed search parameter as it's now client-side)
function getRequests($pdo, $status) {
    $query = "SELECT * FROM service_requests WHERE status = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

$requests = [
    'Pending' => getRequests($pdo, 'Pending'),
    'In Progress' => getRequests($pdo, 'In Progress'),
    'Done' => getRequests($pdo, 'Done')
];

// Calculate statistics
// Set Saudi Arabia timezone
date_default_timezone_set('Asia/Riyadh');

// Calculate current date and time in Riyadh
$currentHour = date('H');
$currentDate = date('Y-m-d');

// Business calculator: ONLY show price if service completed during 3PM-2AM Saudi time
// Set Saudi Arabia timezone
date_default_timezone_set('Asia/Riyadh');

// Calculate current date and time in Riyadh
$currentHour = intval(date('H'));
$currentDate = date('Y-m-d');

// Simple logic: If current time is between 3PM-2AM, show earnings for current business window
if ($currentHour >= 15 || $currentHour < 2) {
    // Business hours active
    if ($currentHour >= 15) {
        // After 3PM: count from today 3PM to tomorrow 2AM
        $startDate = $currentDate . ' 15:00:00';
        $endDate = date('Y-m-d', strtotime('+1 day')) . ' 02:00:00';
    } else {
        // Before 2AM: count from yesterday 3PM to today 2AM
        $startDate = date('Y-m-d', strtotime('-1 day')) . ' 15:00:00';
        $endDate = $currentDate . ' 02:00:00';
    }
    
    $todayEarnings = $pdo->query("SELECT IFNULL(SUM(price), 0) FROM service_requests 
                                  WHERE status = 'Done' 
                                  AND completion_date IS NOT NULL 
                                  AND completion_date >= '$startDate' 
                                  AND completion_date <= '$endDate'")->fetchColumn();
} else {
    // Outside business hours (2AM-3PM): show 0
    $todayEarnings = 0;
}

// Debug info
$debugInfo = "Current: " . date('Y-m-d H:i:s') . " | Hour: $currentHour | Earnings: $todayEarnings";

$stats = [
    'today_earnings' => $todayEarnings,
    'pending_count' => count($requests['Pending']),
    'in_progress_count' => count($requests['In Progress']),
    'debug_info' => $debugInfo
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200, initial-scale=0.3, user-scalable=yes">
    <title>Dashboard - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #0f172a;
            --secondary-dark: #1e293b;
            --accent-blue: #3b82f6;
            --light-blue: #93c5fd;
            --text-light: #f8fafc;
            --pending-color: #f59e0b;
            --progress-color: #3b82f6;
            --done-color: #10b981;
            --whatsapp-green: #25D366;
            --print-gray: #6b7280;
            --delete-red: #ef4444;
        }

        body {
            background-color: var(--primary-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-width: 1200px;
            overflow-x: auto;
        }

        .container {
            min-width: 1200px;
            width: 1200px;
            margin: 0 auto;
            padding: 1rem;
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--secondary-dark);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .quick-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .action-btn {
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .add-btn {
            background-color: var(--accent-blue);
            color: white;
        }

        .warranty-btn {
            background-color: var(--done-color);
            color: white;
            text-decoration: none;
            padding: 0.75rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mini-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
            background-color: var(--secondary-dark);
            padding: 0.75rem;
            border-radius: 0.5rem;
        }

        .mini-stat {
            text-align: center;
        }

        .mini-stat h3 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: var(--text-light);
            opacity: 0.8;
        }

        .mini-stat p {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0;
        }

        .clickable-stat {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            border-radius: 0.5rem;
            position: relative;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
        }

        .clickable-stat:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.1));
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .clickable-stat::after {
            content: '👁️';
            position: absolute;
            top: 5px;
            right: 8px;
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .clickable-stat h3::after {
            content: ' 📋';
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            overflow: auto;
        }

        .modal-content {
            background-color: var(--primary-dark);
            margin: 2% auto;
            padding: 0;
            border-radius: 0.5rem;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--secondary-dark);
            border-bottom: 1px solid #334155;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            padding: 0 10px;
        }

        .close:hover,
        .close:focus {
            color: var(--text-light);
            text-decoration: none;
        }

        .modal-body {
            padding: 1rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-body .search-container {
            margin-bottom: 1rem;
        }

        .modal-body .search-bar {
            background-color: var(--secondary-dark);
            border: 1px solid #334155;
        }

        .no-results-row {
            background-color: var(--secondary-dark) !important;
        }

        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-bar {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: none;
            background-color: var(--secondary-dark);
            color: var(--text-light);
            font-size: 1rem;
        }

        .status-section {
            margin-bottom: 2.5rem;
        }

        .status-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid;
            font-size: 1.25rem;
        }

        .pending-title { color: var(--pending-color); border-color: var(--pending-color); }
        .progress-title { color: var(--progress-color); border-color: var(--progress-color); }
        .done-title { color: var(--done-color); border-color: var(--done-color); opacity: 0.8; }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--secondary-dark);
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .requests-table th, 
        .requests-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        .requests-table th {
            background-color: #1e293b;
            color: var(--text-light);
            font-weight: 500;
        }

        .requests-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.35rem 0.7rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-pending { background-color: rgba(245, 158, 11, 0.2); color: var(--pending-color); }
        .badge-progress { background-color: rgba(59, 130, 246, 0.2); color: var(--progress-color); }
        .badge-done { background-color: rgba(16, 185, 129, 0.2); color: var(--done-color); }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .table-btn {
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            transition: transform 0.2s;
        }

        .table-btn:hover {
            transform: scale(1.1);
        }

        .whatsapp-btn { background-color: var(--whatsapp-green); }
        .print-btn { background-color: var(--print-gray); }
        .delete-btn { background-color: var(--delete-red); }

        .status-select {
            padding: 0.5rem;
            border-radius: 0.25rem;
            background-color: var(--secondary-dark);
            color: var(--text-light);
            border: 1px solid #334155;
            cursor: pointer;
            min-width: 120px;
        }

        .copyright-footer {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            color: #4b5563;
            font-size: 0.7rem;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .copyright-footer a {
            color: inherit;
            text-decoration: none;
        }

        .copyright-footer:hover {
            opacity: 0.8;
        }

        /* Force PC layout on all devices - no responsive changes */
        @media (max-width: 768px) {
            /* Force desktop layout on mobile */
            html {
                min-width: 1200px;
            }
            
            body {
                min-width: 1200px;
                width: 1200px;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }
            
            .container {
                min-width: 1200px;
                width: 1200px;
            }
            
            /* Ensure tables maintain full width */
            .requests-table {
                width: 1200px;
                table-layout: fixed;
            }
            
            /* Keep all grid layouts as-is */
            .mini-stats {
                grid-template-columns: repeat(4, 1fr) !important;
                min-width: 1200px;
            }
            
            /* Ensure buttons stay same size */
            .action-btn, .table-btn, .status-select {
                font-size: inherit !important;
                padding: inherit !important;
            }
            
            /* Modal adjustments for mobile */
            .modal-content {
                width: 1150px !important;
                margin: 20px auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
            <form action="loader.php?page=logout" method="get">
                <button type="submit" class="action-btn" style="background-color: var(--accent-blue);">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-buttons">
                <a href="loader.php?page=add_request" class="action-btn add-btn">
                    <i class="fas fa-plus"></i> Add Service
                </a>
                <a href="loader.php?page=warranty" class="action-btn warranty-btn" title="Warranty Services">
                    <i class="fas fa-shield-alt"></i>
                </a>
            </div>
        </div>

        <!-- Mini Statistics -->
        <div class="mini-stats">
            <div class="mini-stat">
                <h3>Today | اليوم</h3>
                <p><?= number_format($stats['today_earnings'], 2) ?> SAR</p>
                <small style="color: #94a3b8; font-size: 0.6rem;"><?= $stats['debug_info'] ?></small>
            </div>
            <div class="mini-stat">
                <h3>Pending | قيد الإصلاح</h3>
                <p><?= $stats['pending_count'] ?></p>
            </div>
            <div class="mini-stat clickable-stat" onclick="openModal('inProgressModal')" title="Click to view scrollable list">
                <h3>In Progress | بانتظار العميل</h3>
                <p><?= $stats['in_progress_count'] ?></p>
                <small style="color: #94a3b8; font-size: 0.7rem;">Click to view</small>
            </div>
            <div class="mini-stat clickable-stat" onclick="openModal('completedModal')" title="Click to view scrollable list">
                <h3>Completed | مكتمل</h3>
                <p><?= count($requests['Done']) ?></p>
                <small style="color: #94a3b8; font-size: 0.7rem;">Click to view</small>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" id="pendingSearch" class="search-bar" 
                   placeholder="Search Pending Services بحث بواسطة اسم العميل، أو رقم الواتساب، أو رمز التعريف..." 
                   onkeyup="searchTable('pendingTable', 'pendingSearch')">
        </div>

        <!-- Pending Services -->
        <div class="status-section">
            <h2 class="status-title pending-title">
                <i class="fas fa-clock"></i> Pending Services(قيد الإصلاح)
            </h2>
            <?php if (!empty($requests['Pending'])): ?>
            <table class="requests-table" id="pendingTable">
                <thead>
    <tr>
        <th>Token(رقم)</th>
        <th>Customer(اسم العميل)</th>
        <th>WhatsApp</th>
        <th>Device(جهاز)</th>
        <th>Problem(المشكلة)</th>  <!-- Add this new column -->
        <th>Price</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
                <tbody>
                    <?php foreach ($requests['Pending'] as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['service_token']) ?></td>
                        <td><?= htmlspecialchars($request['customer_name']) ?></td>
                        <td><?= htmlspecialchars($request['whatsapp_number']) ?></td>
                        <td><?= htmlspecialchars($request['device_type']) ?></td>
                        <td><?= htmlspecialchars($request['problem_description']) ?></td>
                        <td><?= number_format($request['price'], 2) ?> SAR</td>
                        <td><?= date('d M Y', strtotime($request['created_at'])) ?></td>
                        <td>
                            <span class="status-badge badge-pending">Pending</span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <select class="status-select" data-id="<?= $request['id'] ?>">
                                    <option value="Pending" selected>Pending(قيد الإصلاح)</option>
                                    <option value="In Progress">In Progress(بانتظار العميل)</option>
                                    <option value="Done">Done(تم)</option>
                                </select>
                                <button class="table-btn whatsapp-btn" onclick="window.open('https://wa.me/<?= ltrim($request['whatsapp_number'], '0') ?>?text=<?= urlencode("عزيزي القيمر " . $request['customer_name'] . "، تم إستلام طلب صيانة برقم فاتورة #" . $request['service_token'] . ". قيمة الصيانة: " . $request['price'] . " ريال. يمكنك التواصل معنا عبر الرقم 0557911573. تعتبر هذه الفاتورة موافقة على شروط الصيانة والضمان. شكراً لاختياركم متجر قيمز") ?>', '_blank')">
    <i class="fab fa-whatsapp"></i>
</button>
                                <button class="table-btn print-btn" onclick="printTicket('<?= $request['service_token'] ?>')">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="table-btn delete-btn" data-id="<?= $request['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #94a3b8; padding: 1rem;">No pending(لم يتم العثور على خدمات معلقة)</p>
            <?php endif; ?>
        </div>

        <!-- In Progress Modal -->
        <div id="inProgressModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="status-title progress-title">
                        <i class="fas fa-tools"></i> In Progress(بانتظار العميل) 
                        <span style="font-size: 0.8rem; opacity: 0.7;">📜 Scrollable List</span>
                    </h2>
                    <span class="close" onclick="closeModal('inProgressModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Search Bar for In Progress -->
                    <div class="search-container">
                        <input type="text" id="inProgressSearch" class="search-bar" 
                               placeholder="Search In Progress Services..." 
                               onkeyup="searchTable('inProgressTable', 'inProgressSearch')">
                    </div>
                    <?php if (!empty($requests['In Progress'])): ?>
                    <table class="requests-table" id="inProgressTable">
                        <thead>
                            <tr>
                                <th>Token(رقم)</th>
                                <th>Customer(اسم العميل)</th>
                                <th>WhatsApp</th>
                                <th>Device(جهاز)</th>
                                <th>Problem(المشكلة)</th>
                                <th>Price</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests['In Progress'] as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['service_token']) ?></td>
                                <td><?= htmlspecialchars($request['customer_name']) ?></td>
                                <td><?= htmlspecialchars($request['whatsapp_number']) ?></td>
                                <td><?= htmlspecialchars($request['device_type']) ?></td>
                                <td><?= htmlspecialchars($request['problem_description']) ?></td>
                                <td><?= number_format($request['price'], 2) ?> SAR</td>
                                <td><?= date('d M Y', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge badge-progress">In Progress</span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <select class="status-select" data-id="<?= $request['id'] ?>">
                                            <option value="Pending">Pending(لب جديد)</option>
                                            <option value="In Progress" selected>In Progress(بانتظار العميل)</option>
                                            <option value="Done">Done(تم)</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; padding: 1rem;">No in-progress services found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Completed Modal -->
        <div id="completedModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="status-title done-title">
                        <i class="fas fa-check-circle"></i> Completed(مكتمل) 
                        <span style="font-size: 0.8rem; opacity: 0.7;">📜 Scrollable List</span>
                    </h2>
                    <span class="close" onclick="closeModal('completedModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Search Bar for Completed -->
                    <div class="search-container">
                        <input type="text" id="completedSearch" class="search-bar" 
                               placeholder="Search Completed Services..." 
                               onkeyup="searchTable('completedTable', 'completedSearch')">
                    </div>
                    <?php if (!empty($requests['Done'])): ?>
                    <table class="requests-table" id="completedTable">
                        <thead>
                            <tr>
                                <th>Token(رقم)</th>
                                <th>Customer(اسم العميل)</th>
                                <th>WhatsApp</th>
                                <th>Device(جهاز)</th>
                                <th>Problem(المشكلة)</th>
                                <th>Price</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests['Done'] as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['service_token']) ?></td>
                                <td><?= htmlspecialchars($request['customer_name']) ?></td>
                                <td><?= htmlspecialchars($request['whatsapp_number']) ?></td>
                                <td><?= htmlspecialchars($request['device_type']) ?></td>
                                <td><?= htmlspecialchars($request['problem_description']) ?></td>
                                <td><?= number_format($request['price'], 2) ?> SAR</td>
                                <td><?= date('d M Y', strtotime($request['completion_date'] ?? $request['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge badge-done">Completed</span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <select class="status-select" data-id="<?= $request['id'] ?>">
                                            <option value="Pending">Pending(قيد الإصلاح)</option>
                                            <option value="In Progress">In Progress(بانتظار العميل)</option>
                                            <option value="Done" selected>Done(تم)</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; padding: 1rem;">No completed services found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Copyright Footer -->
        <div class="copyright-footer">
            <a href="https://easin07.github.io/it/" target="_blank">
                Developed by EASIN HOSSEN
            </a>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Search function for tables
        function searchTable(tableId, searchInputId) {
            const input = document.getElementById(searchInputId);
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let found = false;
                
                // Search in Token, Customer Name, WhatsApp, Device, and Problem columns
                const searchColumns = [0, 1, 2, 3, 4]; // Token, Customer, WhatsApp, Device, Problem
                
                searchColumns.forEach(columnIndex => {
                    if (cells[columnIndex] && cells[columnIndex].textContent.toLowerCase().includes(filter)) {
                        found = true;
                    }
                });
                
                if (found || filter === '') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Show "No results found" message if no rows are visible
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            let noResultsRow = table.querySelector('.no-results-row');
            
            if (visibleRows.length === 0 && filter !== '') {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.className = 'no-results-row';
                    noResultsRow.innerHTML = '<td colspan="9" style="text-align: center; color: #94a3b8; padding: 1rem;">No results found</td>';
                    tbody.appendChild(noResultsRow);
                }
                noResultsRow.style.display = '';
            } else if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }
        
        // Status update handler with automatic WhatsApp notifications
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const id = this.dataset.id;
        const status = this.value;
        const originalValue = this.dataset.originalValue;
        const row = this.closest('tr');
        const customerName = row.querySelector('td:nth-child(2)').textContent;
        const whatsappNumber = row.querySelector('td:nth-child(3)').textContent.trim();
        const serviceToken = row.querySelector('td:nth-child(1)').textContent;
        const price = row.querySelector('td:nth-child(6)').textContent;
        
        // Show loading state
        const originalHTML = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        this.disabled = true;
        
        // First update the status in database
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `update_status=1&id=${id}&status=${status}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Store the new status as original value
                this.dataset.originalValue = status;
                
                // Automatic WhatsApp messages for specific statuses
                if (status === 'In Progress' || status === 'Done') {
                    let message = '';
                    if (status === 'In Progress') {
                        message = `عزيزي القيمر ${customerName} لقد تم تحديث حالة الصيانة لديكم برقم الفاتورة #${serviceToken} الصيانة اكتملت وجاهزة للتسليم ${price}`;
                    } else if (status === 'Done') {
    message = `عزيزي القيمر ${customerName} لقد تم تحديث حالة الصيانة لديكم برقم الفاتورة #${serviceToken} تم التسليم بقيمة ${price} ضمانك ٧ أيام من اليوم. شكراً لاختياركم قيمز بارك. قيم خدمتنا على خرائط قوقل: https://maps.app.goo.gl/7ydkPutqLjUpuYdSA`;
}
                    
                    // Open WhatsApp in new tab
                    const whatsappWindow = window.open(
                        `https://wa.me/${whatsappNumber.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`, 
                        '_blank'
                    );
                }
                
                // Small delay before reload to ensure WhatsApp opens
                setTimeout(() => {
                    location.reload();
                }, 500);
                
            } else {
                throw new Error(data.error || 'Update failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Reset to original value
            this.value = originalValue;
            this.innerHTML = originalHTML;
            this.disabled = false;
            
            // Show error message
            alert(`Failed to update status: ${error.message}`);
        });
    });

    // Store the original value when page loads
    select.dataset.originalValue = select.value;
});

        // Delete request handler
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                if (confirm('Are you sure you want to delete this service request?')) {
                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;
                    
                    fetch('api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `delete_request=1&id=${id}`
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Remove the row from the table
                            this.closest('tr').remove();
                            // Optionally: You could update your statistics counters here
                        } else {
                            throw new Error(data.error || 'Delete failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Reset button state
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    });
                }
            });
        });

        // Print ticket function
        function printTicket(token) {
    // Find the row with the matching token
    let row;
    document.querySelectorAll('.requests-table tbody tr').forEach(tr => {
        if (tr.querySelector('td:first-child').textContent.trim() === token) {
            row = tr;
        }
    });
    if (!row) return;

    const shopName = document.querySelector('h1').textContent;
    const customerName = row.querySelector('td:nth-child(2)').textContent;
    const whatsapp = row.querySelector('td:nth-child(3)').textContent;
    const device = row.querySelector('td:nth-child(4)').textContent;
    const problem = row.querySelector('td:nth-child(5)').textContent;  // Get problem description
    const price = row.querySelector('td:nth-child(6)').textContent;
    const date = row.querySelector('td:nth-child(7)').textContent;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Service Ticket ${token}</title>
                <style>
                    @page {
                        size: 40mm 60mm;
                        margin: 0;
                    }
                    body {
                        width: 40mm;
                        height: 60mm;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-family: Arial, sans-serif;
                        background: #fff;
                    }
                    .ticket {
                        width: 38mm;
                        height: 58mm;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        border: 1px dashed #ccc;
                        padding: 2mm;
                        box-sizing: border-box;
                        text-align: center;
                    }
                    .shop {
                        font-size: 11px;
                        font-weight: bold;
                        margin-bottom: 1mm;
                    }
                    .info {
                        font-size: 9px;
                        margin-bottom: 0.8mm;
                        word-break: break-word;
                        line-height: 1.1;
                    }
                </style>
            </head>
            <body onload="window.print()">
                <div class="ticket">
                    <div class="shop">قيمز بارك</div>
                    <div class="info">.........................</div>
                    <div class="info">${token}</div>
                    <div class="info">${customerName}</div>
                    <div class="info">${whatsapp}</div>
                    <div class="info">.........................</div>
                    <div class="shop">${device}</div>
                    <div class="shop">${problem}</div>  <!-- Added problem description -->
                    <div class="info">${price}</div>
                    <div class="info">${date}</div>
                    <div class="info">ضمان لمدة 7 أيام</div>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
}
    </script>
</body>
</htm
