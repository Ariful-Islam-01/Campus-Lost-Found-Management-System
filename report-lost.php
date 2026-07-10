<?php
// report-lost.php
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

// Handle form submission (POST API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $itemName = isset($_POST['itemName']) ? trim($_POST['itemName']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $lastSeenLocation = isset($_POST['lastSeenLocation']) ? trim($_POST['lastSeenLocation']) : '';
    $dateLost = isset($_POST['dateLost']) ? trim($_POST['dateLost']) : '';
    
    $errors = [];
    
    // Server-Side Input Validation
    
    // 1. Item Name
    if (empty($itemName)) {
        $errors['itemName'] = 'Item name is required.';
    } elseif (strlen($itemName) < 2) {
        $errors['itemName'] = 'Item name must be at least 2 characters.';
    } elseif (strlen($itemName) > 100) {
        $errors['itemName'] = 'Item name cannot exceed 100 characters.';
    }
    
    // 2. Category
    $allowedCategories = ['Electronics', 'Books & Stationery', 'Keys & Cards', 'Clothing & Accessories', 'Others'];
    if (empty($category)) {
        $errors['category'] = 'Please select a category.';
    } elseif (!in_array($category, $allowedCategories)) {
        $errors['category'] = 'Invalid category selected.';
    }
    
    // 3. Description
    if (empty($description)) {
        $errors['description'] = 'Description is required.';
    } elseif (strlen($description) < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    } elseif (strlen($description) > 1000) {
        $errors['description'] = 'Description cannot exceed 1000 characters.';
    }
    
    // 4. Last Seen Location
    if (empty($lastSeenLocation)) {
        $errors['lastSeenLocation'] = 'Last seen location is required.';
    } elseif (strlen($lastSeenLocation) < 3) {
        $errors['lastSeenLocation'] = 'Location must be at least 3 characters.';
    } elseif (strlen($lastSeenLocation) > 100) {
        $errors['lastSeenLocation'] = 'Location cannot exceed 100 characters.';
    }
    
    // 5. Date Lost
    if (empty($dateLost)) {
        $errors['dateLost'] = 'Date lost is required.';
    } else {
        // Validate date format and ensure it's not in the future
        $d = DateTime::createFromFormat('Y-m-d', $dateLost);
        if (!$d || $d->format('Y-m-d') !== $dateLost) {
            $errors['dateLost'] = 'Please enter a valid date.';
        } else {
            $today = new DateTime('today');
            if ($d > $today) {
                $errors['dateLost'] = 'Date lost cannot be in the future.';
            }
        }
    }
    
    // Photo upload handling (optional)
    $uploadedPhotoPath = null;
    if (isset($_FILES['itemPhoto']) && $_FILES['itemPhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['itemPhoto'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['itemPhoto'] = 'Failed to upload photo. Error code: ' . $file['error'];
        } else {
            // Validate file size (max 2MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $errors['itemPhoto'] = 'Item photo must be smaller than 2MB.';
            }
            
            // Validate MIME type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors['itemPhoto'] = 'Only JPG, PNG, and WebP formats are allowed.';
            }
            
            if (empty($errors)) {
                // Ensure upload directory exists
                $uploadDir = __DIR__ . '/uploads/lost_items/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique name
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : 'jpg');
                }
                
                $fileName = uniqid('lost_' . $userId . '_', true) . '.' . $extension;
                $destination = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $uploadedPhotoPath = 'uploads/lost_items/' . $fileName;
                } else {
                    $errors['itemPhoto'] = 'Failed to save uploaded photo to disk.';
                }
            }
        }
    }
    
    // If validation fails
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'validation_error',
            'errors' => $errors
        ]);
        exit;
    }
    
    try {
        // Save to Database
        createLostItem($userId, $itemName, $category, $description, $lastSeenLocation, $dateLost, $uploadedPhotoPath);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Lost item report submitted successfully!'
        ]);
        exit;
        
    } catch (Exception $e) {
        // Clean up uploaded file if DB insertion failed
        if ($uploadedPhotoPath && file_exists(__DIR__ . '/' . $uploadedPhotoPath)) {
            @unlink(__DIR__ . '/' . $uploadedPhotoPath);
        }
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save the lost item report. Please try again.'
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Lost Item | Campus Lost &amp; Found</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Stylesheets -->
  <link rel="stylesheet" href="css/report-lost.css">
</head>
<body>

  <!-- Header Navigation -->
  <header>
    <div class="brand-logo-container">
      <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="32" r="32" fill="url(#lgReport)"/>
        <circle cx="28" cy="27" r="10" stroke="white" stroke-width="3" fill="none"/>
        <line x1="35.5" y1="34.5" x2="45" y2="44" stroke="white" stroke-width="3" stroke-linecap="round"/>
        <defs>
          <linearGradient id="lgReport" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
            <stop stop-color="#0D9488"/>
            <stop offset="1" stop-color="#F59E0B"/>
          </linearGradient>
        </defs>
      </svg>
      <span class="brand-logo-title">Campus Lost &amp; Found</span>
    </div>

    <div class="user-menu">
      <span class="user-welcome-name" id="headerUserName"><?php echo htmlspecialchars($user['name']); ?></span>
      <a href="dashboard.php" class="btn-dashboard">Dashboard</a>
    </div>
  </header>

  <!-- Floating Background Particles -->
  <div class="bg-particles" aria-hidden="true">
    <span class="particle" style="--x: 10vw; --y: 15vh; --s: 1.1; --d: 7s;"></span>
    <span class="particle" style="--x: 80vw; --y: 20vh; --s: 0.9; --d: 5s;"></span>
    <span class="particle" style="--x: 75vw; --y: 75vh; --s: 1.3; --d: 8s;"></span>
  </div>

  <div class="page-wrapper">
    <!-- Left Info / Sidebar Panel -->
    <section class="brand-panel" aria-label="Reporting Guidelines">
      <div class="brand-inner">
        <div class="avatar-preview-container" id="avatarPreviewContainer">
          <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Picture" class="avatar-preview-img" />
          <?php else: ?>
            <div class="avatar-fallback"><?php echo htmlspecialchars(substr($user['name'], 0, 1)); ?></div>
          <?php endif; ?>
        </div>
        <h2 class="profile-summary-name"><?php echo htmlspecialchars($user['name']); ?></h2>
        <p class="profile-summary-email"><?php echo htmlspecialchars($user['email']); ?></p>
        <span class="profile-badge">Reporting lost item</span>

        <!-- Guidelines list -->
        <div class="reporting-guidelines">
          <h3>Guidelines</h3>
          <ul>
            <li>
              <strong>Be Specific:</strong> Provide a clear item name and descriptive details (e.g. brand, color, unique marks).
            </li>
            <li>
              <strong>Correct Location:</strong> Mention the specific room, building, or area where the item was last seen.
            </li>
            <li>
              <strong>Accurate Date:</strong> Enter the actual date the item went missing.
            </li>
            <li>
              <strong>Upload Photo:</strong> Adding an image helps matching items much faster!
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Right Form Panel -->
    <section class="form-panel" aria-label="Lost Item Report Form panel">
      <div class="form-card">
        <div class="form-header">
          <h2 class="form-title">Report Lost Item</h2>
          <p class="form-subtitle">Provide details about your missing item to help return it to you.</p>
        </div>

        <!-- Success Toast Notification -->
        <div class="success-toast" id="successToast" role="status" aria-live="polite" aria-hidden="true" style="display: none;">
          <span class="toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </span>
          <div>
            <strong>Report Submitted!</strong>
            <p id="successToastMsg">Your lost item report has been saved successfully.</p>
          </div>
        </div>

        <form id="lostItemForm" class="report-form" enctype="multipart/form-data" novalidate>
          
          <!-- Item Name -->
          <div class="field-group" id="fieldGroupName">
            <label class="field-label" for="itemName">
              Item Name <span class="required-star">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
              </span>
              <input type="text" id="itemName" name="itemName" class="form-input" placeholder="e.g. Black Leather Wallet, Blue Backpack" maxlength="100" aria-describedby="itemNameMsg" required />
              <span class="input-status-icon" id="statusIconName" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="itemNameMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Category -->
          <div class="field-group" id="fieldGroupCategory">
            <label class="field-label" for="category">
              Category <span class="required-star">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
              </span>
              <select id="category" name="category" class="form-input form-select" aria-describedby="categoryMsg" required>
                <option value="" disabled selected>Select category...</option>
                <option value="Electronics">Electronics</option>
                <option value="Books &amp; Stationery">Books &amp; Stationery</option>
                <option value="Keys &amp; Cards">Keys &amp; Cards</option>
                <option value="Clothing &amp; Accessories">Clothing &amp; Accessories</option>
                <option value="Others">Others</option>
              </select>
              <span class="input-status-icon" id="statusIconCategory" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="categoryMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Description -->
          <div class="field-group" id="fieldGroupDescription">
            <label class="field-label" for="description">
              Description <span class="required-star">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" style="top: 1.25rem;" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              </span>
              <textarea id="description" name="description" class="form-input form-textarea" placeholder="Describe the item (color, brand, serial keys, stickers, contents inside)..." maxlength="1000" aria-describedby="descriptionMsg" required></textarea>
              <span class="input-status-icon" id="statusIconDescription" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="descriptionMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Last Seen Location -->
          <div class="field-group" id="fieldGroupLocation">
            <label class="field-label" for="lastSeenLocation">
              Last Seen Location <span class="required-star">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </span>
              <input type="text" id="lastSeenLocation" name="lastSeenLocation" class="form-input" placeholder="e.g. Science Building Cafeteria, Room 402" maxlength="100" aria-describedby="lastSeenLocationMsg" required />
              <span class="input-status-icon" id="statusIconLocation" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="lastSeenLocationMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Date Lost -->
          <div class="field-group" id="fieldGroupDate">
            <label class="field-label" for="dateLost">
              Date Lost <span class="required-star">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </span>
              <input type="date" id="dateLost" name="dateLost" class="form-input" aria-describedby="dateLostMsg" max="<?php echo date('Y-m-d'); ?>" required />
              <span class="input-status-icon" id="statusIconDate" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="dateLostMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Photo Upload (Optional) -->
          <div class="field-group" id="fieldGroupPhoto">
            <label class="field-label" for="itemPhoto">
              Item Photo (Optional)
            </label>
            <div class="file-upload-wrapper">
              <label class="file-upload-btn-label" for="itemPhoto">
                Choose Image File
              </label>
              <input type="file" id="itemPhoto" name="itemPhoto" class="file-input-hidden" accept="image/jpeg,image/png,image/webp" aria-describedby="itemPhotoMsg" />
              <span class="file-upload-name-preview" id="fileNamePreview">No file chosen (JPG, PNG, WebP. Max 2MB)</span>
            </div>
            <div class="field-message" id="itemPhotoMsg" role="alert" aria-live="polite"></div>
            
            <!-- Dynamic Image Preview Container -->
            <div class="item-photo-preview-container" id="itemPhotoPreviewContainer" style="display: none;">
              <img src="" id="itemPhotoPreviewImg" alt="Lost Item Preview" />
              <button type="button" class="btn-remove-preview" id="btnRemovePreview" aria-label="Remove photo preview">&times;</button>
            </div>
          </div>

          <!-- Submit Button -->
          <button type="submit" id="submitBtn" class="btn-submit">
            <span class="btn-text">Submit Lost Report</span>
            <span class="btn-loader" id="btnLoader" aria-label="Submitting report">
              <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.2)"></circle>
                <circle cx="12" cy="12" r="10" stroke="white" stroke-width="3"></circle>
              </svg>
            </span>
          </button>

        </form>
      </div>
    </section>
  </div>

  <!-- Page Javascript -->
  <script src="js/report-lost.js"></script>
</body>
</html>
