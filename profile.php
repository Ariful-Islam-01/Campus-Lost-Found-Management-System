<?php
// profile.php
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

// Handle profile update request (POST API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $name = isset($_POST['fullName']) ? trim($_POST['fullName']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    $errors = [];
    
    // Server-Side Input Validation
    if (empty($name)) {
        $errors['fullName'] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors['fullName'] = 'Name must be at least 2 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s'.'-]+$/", $name)) {
        $errors['fullName'] = 'Name should contain only letters and spaces.';
    }
    
    if (!empty($phone) && !preg_match("/^[0-9\s\-\+\(\)]+$/", $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    
    // Profile photo upload handling
    $uploadedPhotoPath = null;
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profilePhoto'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['profilePhoto'] = 'Failed to upload photo. Error code: ' . $file['error'];
        } else {
            // Validate file size (max 2MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $errors['profilePhoto'] = 'Profile photo must be smaller than 2MB.';
            }
            
            // Validate MIME type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors['profilePhoto'] = 'Only JPG, PNG, and WebP formats are allowed.';
            }
            
            if (empty($errors)) {
                // Ensure directory exists
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique name
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : 'jpg');
                }
                
                $fileName = uniqid('profile_' . $userId . '_', true) . '.' . $extension;
                $destination = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $uploadedPhotoPath = 'uploads/profile_photos/' . $fileName;
                    
                    // Delete old profile photo if it exists
                    if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])) {
                        @unlink(__DIR__ . '/' . $user['profile_photo']);
                    }
                } else {
                    $errors['profilePhoto'] = 'Failed to save uploaded photo to disk.';
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
        // Update database
        updateUserProfile($userId, $name, $phone, $uploadedPhotoPath);
        
        // Update session parameters
        $_SESSION['user_name'] = $name;
        
        // Fetch fresh details
        $updatedUser = getUserById($userId);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully!',
            'user' => [
                'name' => $updatedUser['name'],
                'phone' => $updatedUser['phone'] ?? '',
                'profile_photo' => $updatedUser['profile_photo'] ?? null
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save profile changes. Please try again.'
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
  <title>Edit Profile | Campus Lost & Found</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Stylesheets -->
  <link rel="stylesheet" href="css/profile.css">
</head>
<body>

  <!-- Header Navigation -->
  <header>
    <div class="brand-logo-container">
      <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="32" r="32" fill="url(#lg3)"/>
        <circle cx="28" cy="27" r="10" stroke="white" stroke-width="3" fill="none"/>
        <line x1="35.5" y1="34.5" x2="45" y2="44" stroke="white" stroke-width="3" stroke-linecap="round"/>
        <defs>
          <linearGradient id="lg3" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
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
    <span class="particle" style="--x: 5vw;  --y: 20vh; --s: 1.0; --d: 6s;"></span>
    <span class="particle" style="--x: 75vw; --y: 15vh; --s: 0.8; --d: 8s;"></span>
    <span class="particle" style="--x: 80vw; --y: 70vh; --s: 1.2; --d: 9s;"></span>
  </div>

  <div class="page-wrapper">
    <!-- Left Summary Panel -->
    <section class="brand-panel" aria-label="Profile Summary">
      <div class="brand-inner">
        <!-- Circular avatar wrapper -->
        <div class="avatar-preview-container" id="avatarPreviewContainer">
          <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Picture" class="avatar-preview-img" id="avatarPreviewImg" />
          <?php else: ?>
            <div class="avatar-fallback" id="avatarFallback"><?php echo htmlspecialchars(substr($user['name'], 0, 1)); ?></div>
          <?php endif; ?>
        </div>
        <h2 class="profile-summary-name" id="summaryUserName"><?php echo htmlspecialchars($user['name']); ?></h2>
        <p class="profile-summary-email"><?php echo htmlspecialchars($user['email']); ?></p>
        <span class="profile-badge">Student Member</span>
      </div>
    </section>

    <!-- Right Profile Editor Panel -->
    <section class="form-panel" aria-label="Profile edit form panel">
      <div class="form-card">
        <div class="form-header">
          <h2 class="form-title">Edit Profile</h2>
          <p class="form-subtitle">Update your personal information and contact details.</p>
        </div>

        <!-- Success Toast Notification -->
        <div class="success-toast" id="successToast" role="status" aria-live="polite" aria-hidden="true" style="display: none;">
          <span class="toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </span>
          <div>
            <strong>Success!</strong>
            <p id="successToastMsg">Your profile has been saved.</p>
          </div>
        </div>

        <form id="profileForm" class="profile-form" enctype="multipart/form-data" novalidate>
          
          <!-- Full Name -->
          <div class="field-group" id="fieldGroupName">
            <label class="field-label" for="fullName">
              Full Name
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input type="text" id="fullName" name="fullName" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Ariful Islam" maxlength="80" aria-describedby="fullNameMsg" />
              <span class="input-status-icon" id="statusIconName" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="fullNameMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Email Address (Disabled / Read-only) -->
          <div class="field-group">
            <label class="field-label" for="emailAddress">
              Email Address (Cannot be changed)
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </span>
              <input type="email" id="emailAddress" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled />
            </div>
          </div>

          <!-- Phone Number -->
          <div class="field-group" id="fieldGroupPhone">
            <label class="field-label" for="phone">
              Phone Number
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              </span>
              <input type="text" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g. +88017XXXXXXXX" maxlength="20" aria-describedby="phoneMsg" />
              <span class="input-status-icon" id="statusIconPhone" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="phoneMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Profile Photo Uploader -->
          <div class="field-group" id="fieldGroupPhoto">
            <label class="field-label" for="profilePhoto">
              Profile Photo
            </label>
            <div class="file-upload-wrapper">
              <label class="file-upload-btn-label" for="profilePhoto">
                Choose Image File
              </label>
              <input type="file" id="profilePhoto" name="profilePhoto" class="file-input-hidden" accept="image/jpeg,image/png,image/webp" aria-describedby="profilePhotoMsg" />
              <span class="file-upload-name-preview" id="fileNamePreview">No file chosen (JPG, PNG, WebP. Max 2MB)</span>
            </div>
            <div class="field-message" id="profilePhotoMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Submit Button -->
          <button type="submit" id="submitBtn" class="btn-submit">
            <span class="btn-text">Save Changes</span>
            <span class="btn-loader" id="btnLoader" aria-label="Saving changes">
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

  <!-- Profile Management Javascript -->
  <script src="js/profile.js"></script>
</body>
</html>
