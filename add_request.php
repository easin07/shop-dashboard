 <?php
 
 require_once('/home/gmpsvasy/public_html/config.php');

// Set headers for UTF-8
header('Content-Type: text/html; charset=utf-8');

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $required = ['customer_name', 'whatsapp_number', 'device_type', 'problem_description', 'price'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All fields are required");
            }
        }

        // Process WhatsApp number - must be exactly 10 digits starting with 0
        $whatsapp_number = trim($_POST['whatsapp_number']);
        
        // Remove any non-digit characters
        $whatsapp_number = preg_replace('/[^0-9]/', '', $whatsapp_number);
        
        // Validate the number format (must be 10 digits starting with 0)
        if (!preg_match('/^0\d{9}$/', $whatsapp_number)) {
            throw new Exception("WhatsApp number must be 10 digits starting with 0 (e.g., 0536598870)");
        }
        
        // Convert to international format by replacing 0 with +966
        $whatsapp_number = '+966' . substr($whatsapp_number, 1);

        // Generate unique service token
        $token = 'GMP-' . substr(str_replace(['a', 'b', 'c', 'd', 'e', 'f'], '', md5(uniqid(rand(), true))), 0, 6);

        // Insert new service
        $stmt = $pdo->prepare("INSERT INTO service_requests 
            (customer_name, whatsapp_number, device_type, problem_description, price, service_token, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        
        $stmt->execute([
            htmlspecialchars($_POST['customer_name']),
            $whatsapp_number,
            htmlspecialchars($_POST['device_type']),
            htmlspecialchars($_POST['problem_description']),
            floatval($_POST['price']),
            $token
        ]);

        // Show success message and clear form
        $success = "Service added successfully! Token: $token";
        $_POST = []; // Clear form
        
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #0f172a;
            --secondary-dark: #1e293b;
            --accent-blue: #3b82f6;
            --text-light: #f8fafc;
            --error-red: #ef4444;
            --success-green: #10b981;
        }

        body {
            background-color: var(--primary-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
            box-sizing: border-box;
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
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-card {
            background-color: var(--secondary-dark);
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.25rem;
            border: 1px solid #334155;
            background-color: var(--primary-dark);
            color: var(--text-light);
            font-size: 1rem;
            box-sizing: border-box;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .submit-btn {
            background-color: var(--accent-blue);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            margin-top: 0.5rem;
            transition: background-color 0.2s;
        }

        .submit-btn:hover {
            background-color: #2563eb;
        }

        .error-message {
            color: var(--error-red);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-message {
            color: var(--success-green);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(16, 185, 129, 0.1);
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 0.5rem;
                width: 100%;
            }
            
            .form-card {
                padding: 1rem;
            }
        }
        /* Style for custom problem container */
        .custom-problem-container {
            margin-top: 10px;
            display: none;
        }
        
        /* Hide the select when not needed */
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>إضافة خدمة جديدة</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="serviceForm">
                <div class="form-group">
                    <label for="customer_name">(اسم العميل) Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" 
                           value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="whatsapp_number">WhatsApp Number *</label>
                    <input type="tel" id="whatsapp_number" name="whatsapp_number" 
                           value="<?= htmlspecialchars($_POST['whatsapp_number'] ?? '') ?>" 
                           placeholder="05xxxxxxxx" pattern="0[0-9]{9}" required>
                    <small style="opacity: 0.7;">Format: 05xxxxxxxx (10 أرقام تبدأ بصفر)</small>
                </div>

                <div class="form-group">
                    <label for="device_type">(نوع الجهاز) Device Type *</label>
                    <select id="device_type" name="device_type" required>
                        <option value="Controller" <?= (isset($_POST['device_type']) && $_POST['device_type'] === 'Controller') ? 'selected' : 'selected' ?>>Controller</option>
                        <option value="PC" <?= (isset($_POST['device_type']) && $_POST['device_type'] === 'PC') ? 'selected' : '' ?>>PC</option>
                        <option value="PS4" <?= (isset($_POST['device_type']) && $_POST['device_type'] === 'PS4') ? 'selected' : '' ?>>PS4</option>
                        <option value="PS5" <?= (isset($_POST['device_type']) && $_POST['device_type'] === 'PS5') ? 'selected' : '' ?>>PS5</option>
                        <option value="Custom" <?= (isset($_POST['device_type']) && $_POST['device_type'] === 'Custom') ? 'selected' : '' ?>>Custom</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="problem_description">(وصف المشكلة) Problem Description *</label>
                    <div id="problem_select_container">
                        <select id="problem_description_select">
                            <!-- Options will be filled by JavaScript -->
                        </select>
                    </div>
                    
                    <div id="custom_problem_container" class="custom-problem-container">
                        <textarea id="problem_description_text" 
                                  placeholder="Please describe the problem"></textarea>
                    </div>
                    <!-- This is the actual field that gets submitted -->
                    <input type="hidden" id="problem_description" name="problem_description" value="">
                </div>

                <div class="form-group">
                    <label for="price">(السعر) Price (SAR) *</label>
                    <input type="number" id="price" name="price" step="0.00" min="0" 
                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> (حفظ الخدمة) Start
                </button>
            </form>
        </div>
    </div>

    <script>
        function updateProblemOptions() {
            const deviceType = document.getElementById('device_type').value;
            const problemSelect = document.getElementById('problem_description_select');
            const problemSelectContainer = document.getElementById('problem_select_container');
            const customProblemContainer = document.getElementById('custom_problem_container');
            
            if (deviceType === 'Custom') {
                // Show custom field and hide dropdown
                problemSelectContainer.classList.add('hidden');
                customProblemContainer.style.display = 'block';
            } else {
                // Show dropdown and hide custom field
                problemSelectContainer.classList.remove('hidden');
                customProblemContainer.style.display = 'none';
                
                // Set options based on device type
                let options = '';
                if (deviceType === 'Controller') {
                    options = `
                        <option value="">Select a problem</option>
                        <option value="L3">L3</option>
                        <option value="R3">R3</option>
                        <option value="Carbon">Carbon</option>
                        <option value="Button">Button</option>
                        <option value="Charge-Port">Charge-Port</option>
                        <option value="Audio-Port">Audio-Port</option>
                        <option value="Motherboard">Motherboard</option>
                        <option value="Battery">Battery</option>
                        <option value="L3+R3">L3+R3</option>
                        <option value="L3+R3+Carbon">L3+R3+Carbon</option>
                        <option value="L3+R3+Carbon+Button">L3+R3+Carbon+Button</option>
                    `;
                } else if (deviceType === 'PC') {
                    options = `
                        <option value="">Select a problem</option>
                        <option value="Check">Check</option>
                        <option value="Clean">Clean</option>
                        <option value="PC Build">PC Build</option>
                        <option value="Re-Build">Re-Build</option>
                        <option value="Windows">Windows</option>
                        <option value="PC Reset">PC Reset</option>
                        <option value="BIOS">BIOS</option>
                        <option value="Replace Parts">Replace Parts</option>
                        <option value="No Power">No Power</option>
                        <option value="Blue Screen">Blue Screen</option>
                        <option value="Driver Error">Driver Error</option>
                    `;
                } else if (deviceType === 'PS4') {
                    options = `
                        <option value="">Select a problem</option>
                        <option value="Check">Check</option>
                        <option value="Clean">Clean</option>
                        <option value="HDMI">HDMI</option>
                        <option value="Disply-IC">Disply-IC</option>
                        <option value="Power">Power</option>
                        <option value="CD-Rom">CD-Rom</option>
                    `;
                } else if (deviceType === 'PS5') {
                    options = `
                        <option value="">Select a problem</option>
                        <option value="Check">Check</option>
                        <option value="Clean">Clean</option>
                        <option value="HDMI">HDMI</option>
                        <option value="Disply-IC">Disply-IC</option>
                        <option value="Power">Power</option>
                    `;
                }
                problemSelect.innerHTML = options;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateProblemOptions();
            
            // Handle device type change
            document.getElementById('device_type').addEventListener('change', updateProblemOptions);
            
            // Handle form submission
            document.getElementById('serviceForm').addEventListener('submit', function(e) {
                const deviceType = document.getElementById('device_type').value;
                const problemDescription = document.getElementById('problem_description');
                const problemSelect = document.getElementById('problem_description_select');
                const problemText = document.getElementById('problem_description_text');
                
                // Set the value that will be submitted
                if (deviceType === 'Custom') {
                    problemDescription.value = problemText.value;
                    // Also set device_type to "Custom" to be clear
                    document.getElementById('device_type').value = 'Custom';
                } else {
                    problemDescription.value = problemSelect.value;
                }
                
                // Validate the problem description is not empty
                if (problemDescription.value.trim() === '') {
                    e.preventDefault();
                    alert('Please provide a problem description');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html