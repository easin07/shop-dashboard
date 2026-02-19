<?php
// warranty.php - This file is on GitHub
require_once('/home/gmpsvasy/public_html/config.php');

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Riyadh');

// Search functionality - UPDATED to work with loader
$search = $_GET['search'] ?? '';

// Get warranty services (completed in last 7 days)
$sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$query = "SELECT *, 
          DATE_ADD(completion_date, INTERVAL 7 DAY) AS warranty_end,
          DATEDIFF(DATE_ADD(completion_date, INTERVAL 7 DAY), CURDATE()) AS days_remaining
          FROM service_requests 
          WHERE status = 'Done' 
          AND completion_date IS NOT NULL
          AND completion_date >= ?";

if (!empty($search)) {
    $query .= " AND (customer_name LIKE ? OR whatsapp_number LIKE ? OR service_token LIKE ?)";
}
$query .= " ORDER BY completion_date DESC";

$stmt = $pdo->prepare($query);
if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->execute([$sevenDaysAgo, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt->execute([$sevenDaysAgo]);
}
$warrantyRequests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Services - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #0f172a;
            --secondary-dark: #1e293b;
            --accent-blue: #3b82f6;
            --text-light: #f8fafc;
            --done-color: #10b981;
            --whatsapp-green: #25D366;
        }

        body {
            background-color: var(--primary-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--secondary-dark);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .back-btn {
            background-color: var(--accent-blue);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .warranty-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: var(--done-color);
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--secondary-dark);
            border-radius: 0.5rem;
            overflow: hidden;
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

        .warranty-badge {
            padding: 0.35rem 0.7rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--done-color);
        }

        .action-btn {
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .whatsapp-btn {
            background-color: var(--whatsapp-green);
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: white;
            border-radius: 0.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warranty-period {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        .urgent {
            color: #f59e0b;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .back-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Warranty Services</h1>
            <a href="loader.php?page=dashboard" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Search Bar - FIXED: Now points to loader.php -->
        <div class="search-container">
            <form method="GET" action="loader.php">
                <input type="hidden" name="page" value="warranty">
                <input type="text" name="search" class="search-bar" 
                       placeholder="Search warranty services by name, number, or token..." 
                       value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <div class="warranty-header">
            <i class="fas fa-shield-alt"></i>
            <h2>خدمات الضمان النشطة (آخر 7 أيام)</h2>
        </div>

        <table class="requests-table">
            <thead>
                <tr>
                    <th>Token (رقم)</th>
                    <th>Customer (اسم العميل)</th>
                    <th>Device (جهاز)</th>
                    <th>Completed</th>
                    <th>Warranty Until</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warrantyRequests)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            لم يتم العثور على خدمات ضمان نشطة في آخر 7 أيام
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($warrantyRequests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['service_token']) ?></td>
                        <td><?= htmlspecialchars($request['customer_name']) ?></td>
                        <td><?= htmlspecialchars($request['device_type']) ?></td>
                        <td>
                            <?= date('d M Y', strtotime($request['completion_date'])) ?>
                            <div class="warranty-period">
                                <?php if ($request['days_remaining'] <= 2): ?>
                                    <span class="urgent">(Only <?= $request['days_remaining'] ?> days left!)</span>
                                <?php else: ?>
                                    (<?= $request['days_remaining'] ?> الأيام المتبقية)
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= date('d M Y', strtotime($request['warranty_end'])) ?></td>
                        <td>
                            <a href="https://wa.me/<?= ltrim($request['whatsapp_number'], '0') ?>?text=<?= urlencode("Hello " . $request['customer_name'] . ", بخصوص ضمانك لـِـ " . $request['device_type'] . " (Token: " . $request['service_token'] . "). الضمان ساري حتى " . date('d M Y', strtotime($request['warranty_end'])) . ". " . ($request['days_remaining'] <= 2 ? "⚠️ الضمان يوشك على الانتهاء!" : "")) ?>" 
                               class="whatsapp-btn" target="_blank">
                                <i class="fab fa-whatsapp"></i> Contact
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
