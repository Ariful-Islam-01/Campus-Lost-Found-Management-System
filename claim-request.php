<?php
// claim-request.php
session_start();

// Block access if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Fallback if user session is invalid
if (!$user) {
    header('Location: login.php?action=logout');
    exit;
}

// Parse request parameters
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$item = getFoundItemById($itemId);

// Redirect back if item does not exist or is not Found
if (!$item || $item['status'] !== 'Found') {
    header('Location: dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $proofOfOwnership = isset($_POST['proofOfOwnership']) ? trim($_POST['proofOfOwnership']) : '';
    
    $errors = [];
    
    // Validate proof of ownership
    if (empty($proofOfOwnership)) {
        $errors['proofOfOwnership'] = 'Proof of ownership is required.';
    } elseif (strlen($proofOfOwnership) < 20) {
        $errors['proofOfOwnership'] = 'Proof of ownership must be at least 20 characters.';
    } elseif (strlen($proofOfOwnership) > 2000) {
        $errors['proofOfOwnership'] = 'Proof of ownership cannot exceed 2000 characters.';
    }
    
    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }
    
    // Create the claim
    if (createClaim($itemId, $userId, $proofOfOwnership)) {
        echo json_encode(['status' => 'success', 'message' => 'Claim submitted successfully!']);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit claim. Please try again.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item | Campus Lost & Found</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --clr-teal-900: #042f2e;
            --clr-teal-800: #134e4a;
            --clr-teal-700: #0f766e;
            --clr-teal-600: #0d9488;
            --clr-teal-500: #14b8a6;
            --clr-teal-400: #2dd4bf;
            --clr-teal-300: #5eead4;
            --clr-amber-400: #fbbf24;
            --clr-white: #ffffff;
            --clr-gray-100: #f3f4f6;
            --clr-gray-200: #e5e7eb;
            --clr-gray-300: #d1d5db;
            --clr-gray-400: #9ca3af;
            --clr-gray-500: #6b7280;
            --clr-gray-800: #1f2937;
            --clr-gray-900: #111827;
            --clr-red-500: #ef4444;
            --ff-base: 'Inter', system-ui, sans-serif;
            --radius-md: 12px;
            --radius-lg: 20px;
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, .18), 0 8px 24px rgba(0, 0, 0, .10);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--ff-base);
            background: linear-gradient(180deg, #101827 0%, #0c1420 100%);
            color: var(--clr-white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header styling */
        header {
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .brand-logo-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 40%, var(--clr-teal-300));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .user-welcome-name {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--clr-white);
            text-decoration: none;
            transition: color 0.25s ease;
        }

        .user-welcome-name:hover {
            color: var(--clr-teal-300);
        }

        .user-welcome-email {
            display: block;
            font-size: 0.75rem;
            color: var(--clr-gray-400);
        }

        .header-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--clr-teal-500);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--clr-gray-800);
            text-decoration: none;
            transition: border-color 0.25s ease;
        }

        .header-avatar:hover {
            border-color: var(--clr-amber-400);
        }

        .header-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-avatar-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
        }

        main {
            flex: 1;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .form-container {
            width: 100%;
            max-width: 700px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 40%, var(--clr-teal-300));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-header p {
            font-size: 0.95rem;
            color: var(--clr-gray-400);
        }

        .item-info {
            background: rgba(13, 148, 136, 0.1);
            border-left: 4px solid var(--clr-teal-500);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
        }

        .item-info h3 {
            font-size: 0.9rem;
            color: var(--clr-gray-400);
            margin-bottom: 0.25rem;
        }

        .item-info p {
            font-size: 1rem;
            color: var(--clr-white);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-group label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--clr-white);
            margin-bottom: 0.5rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 1rem;
            background: rgba(31, 41, 55, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            color: var(--clr-white);
            font-family: var(--ff-base);
            font-size: 0.95rem;
            resize: vertical;
            min-height: 200px;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--clr-teal-500);
            background: rgba(31, 41, 55, 1);
        }

        .form-group textarea::placeholder {
            color: var(--clr-gray-500);
        }

        .error-message {
            color: var(--clr-red-500);
            font-size: 0.85rem;
            margin-top: 0.35rem;
            display: none;
        }

        .error-message.visible {
            display: block;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--clr-teal-600) 0%, var(--clr-teal-500) 100%);
            color: var(--clr-white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(13, 148, 136, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--clr-teal-400);
            border: 1.5px solid var(--clr-teal-400);
        }

        .btn-secondary:hover {
            background: rgba(13, 148, 136, 0.1);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: none;
        }

        .success-message.visible {
            display: block;
        }

        @media (max-width: 600px) {
            .form-container {
                padding: 1.5rem;
            }

            .form-header h1 {
                font-size: 1.35rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="brand-logo-container">
            <div class="brand-title">🔍 Campus Lost & Found</div>
        </div>
        <div class="user-menu">
            <div class="user-info-text">
                <a href="profile.php" class="user-welcome-name"><?php echo htmlspecialchars($user['name']); ?></a>
                <span class="user-welcome-email"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <a href="dashboard.php?action=logout" class="header-avatar" title="Logout">
                <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile" class="header-avatar-img">
                <?php else: ?>
                    <div class="header-avatar-fallback"><?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?></div>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main>
        <div class="form-container">
            <div class="form-header">
                <h1>Claim Found Item</h1>
                <p>Provide proof of ownership to claim this item</p>
            </div>

            <div class="item-info">
                <h3>Item Details</h3>
                <p><?php echo htmlspecialchars($item['item_name']); ?> - <?php echo htmlspecialchars($item['category']); ?></p>
            </div>

            <div class="success-message" id="successMessage"></div>

            <form id="claimForm" method="POST" action="">
                <div class="form-group">
                    <label for="proofOfOwnership">Proof of Ownership</label>
                    <textarea id="proofOfOwnership" name="proofOfOwnership" placeholder="Describe your proof of ownership. Include details such as:&#10;- Purchase receipt details (store, date, amount)&#10;- Unique identifiers (serial number, engraving, custom markings)&#10;- Specific features or accessories&#10;- Any other distinguishing characteristics&#10;&#10;This information helps verify your claim."></textarea>
                    <div class="error-message" id="proofOfOwnershipError"></div>
                </div>

                <div class="form-actions">
                    <a href="item-detail.php?id=<?php echo $itemId; ?>&type=found" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Submit Claim</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const form = document.getElementById('claimForm');
        const successMessage = document.getElementById('successMessage');
        const proofOfOwnershipError = document.getElementById('proofOfOwnershipError');
        const proofOfOwnershipField = document.getElementById('proofOfOwnership');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous errors
            proofOfOwnershipError.textContent = '';
            proofOfOwnershipError.classList.remove('visible');
            successMessage.classList.remove('visible');

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    successMessage.textContent = result.message;
                    successMessage.classList.add('visible');
                    form.reset();
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else if (result.errors) {
                    if (result.errors.proofOfOwnership) {
                        proofOfOwnershipError.textContent = result.errors.proofOfOwnership;
                        proofOfOwnershipError.classList.add('visible');
                    }
                } else {
                    alert(result.message || 'An error occurred. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    </script>
</body>

</html>
