<?php
// item-detail.php
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
$itemType = isset($_GET['type']) ? trim($_GET['type']) : '';

if ($itemId <= 0 || !in_array($itemType, ['lost', 'found'])) {
  header('Location: dashboard.php');
  exit;
}

$item = null;
if ($itemType === 'lost') {
  $item = getLostItemById($itemId);
} else {
  $item = getFoundItemById($itemId);
}

// Redirect back to dashboard if item does not exist
if (!$item) {
  header('Location: dashboard.php');
  exit;
}

// Process details specific to the report type
$isLost = ($itemType === 'lost');
$itemName = $item['item_name'];
$category = $item['category'];
$description = $item['description'];
$status = $item['status'];
$dateReported = $isLost ? $item['date_lost'] : $item['created_at'];
$location = $isLost ? $item['last_seen_location'] : $item['pickup_location'];
$photoPath = $item['photo_path'];

$contactName = $isLost ? $item['reporter_name'] : $item['finder_name'];
$contactEmail = $isLost ? $item['reporter_email'] : $item['finder_email'];
$contactPhone = $isLost ? $item['reporter_phone'] : $item['finder_phone'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($itemName); ?> | Campus Lost &amp; Found</title>

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
      --clr-emerald-400: #34d399;
      --clr-emerald-500: #10b981;
      --clr-red-400: #f87171;
      --clr-white: #ffffff;
      --clr-gray-100: #f3f4f6;
      --clr-gray-200: #e5e7eb;
      --clr-gray-300: #d1d5db;
      --clr-gray-400: #9ca3af;
      --clr-gray-500: #6b7280;
      --clr-gray-800: #1f2937;
      --clr-gray-900: #111827;
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

    .user-info-text {
      text-align: right;
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
      background: linear-gradient(135deg, var(--clr-teal-600), var(--clr-amber-500));
      color: var(--clr-white);
      font-size: 0.95rem;
      font-weight: 700;
      text-transform: uppercase;
    }

    .btn-logout {
      padding: 0.55rem 1.15rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--clr-white);
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.25s ease;
    }

    .btn-logout:hover {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.4);
      color: #ef4444;
    }

    /* Main layout container */
    main {
      flex: 1;
      max-width: 1000px;
      width: 100%;
      margin: 3rem auto;
      padding: 0 2rem;
    }

    /* Back Button */
    .btn-back-container {
      margin-bottom: 2rem;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--clr-gray-300);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      padding: 0.5rem 1rem;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 8px;
      transition: all 0.25s ease;
    }

    .btn-back:hover {
      background: rgba(255, 255, 255, 0.08);
      color: var(--clr-white);
      border-color: var(--clr-teal-500);
      transform: translateX(-2px);
    }

    .btn-back svg {
      width: 16px;
      height: 16px;
    }

    /* Details Panel Split Grid */
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1.2fr;
      gap: 2.5rem;
      align-items: start;
    }

    /* Photo Container Panel */
    .detail-photo-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      backdrop-filter: blur(12px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .detail-photo-wrapper {
      width: 100%;
      height: 380px;
      background: rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-photo-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .detail-photo-fallback-icon {
      color: var(--clr-teal-500);
      opacity: 0.3;
    }

    .detail-badge-group {
      position: absolute;
      top: 16px;
      right: 16px;
      display: flex;
      gap: 0.5rem;
      z-index: 2;
    }

    .status-badge {
      padding: 0.4rem 0.85rem;
      border-radius: 99px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    .status-badge.status-lost {
      background: var(--clr-red-400);
      color: #111827;
      border: 1px solid rgba(248, 113, 113, 0.4);
    }

    .status-badge.status-found {
      background: var(--clr-emerald-400);
      color: #111827;
      border: 1px solid rgba(16, 185, 129, 0.4);
    }

    .status-badge.status-returned,
    .status-badge.status-claimed {
      background: var(--clr-gray-500);
      color: var(--clr-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .category-badge {
      position: absolute;
      top: 16px;
      left: 16px;
      padding: 0.4rem 0.85rem;
      border-radius: 99px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      background: rgba(13, 148, 136, 0.85);
      color: white;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      z-index: 2;
    }

    /* Info Content Panel */
    .detail-info-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      box-shadow: var(--shadow-lg);
      backdrop-filter: blur(12px);
    }

    .item-title-section {
      margin-bottom: 1.75rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 1.25rem;
    }

    .item-detail-title {
      font-size: 1.85rem;
      font-weight: 800;
      background: linear-gradient(135deg, #fff 50%, var(--clr-teal-300));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 0.35rem;
    }

    .item-detail-type {
      font-size: 0.8rem;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: var(--clr-teal-400);
    }

    /* Detail Specs Grid */
    .specs-grid {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .spec-item {
      display: flex;
      align-items: flex-start;
      gap: 0.85rem;
    }

    .spec-icon-wrapper {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      background: rgba(13, 148, 136, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--clr-teal-400);
      flex-shrink: 0;
    }

    .spec-icon-wrapper svg {
      width: 18px;
      height: 18px;
    }

    .spec-details {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .spec-label {
      font-size: 0.72rem;
      color: var(--clr-gray-400);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.05em;
    }

    .spec-value {
      font-size: 0.95rem;
      color: var(--clr-white);
      line-height: 1.4;
    }

    /* Contact Details Card */
    .contact-info-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      padding: 1.5rem;
      margin-top: 2rem;
    }

    .contact-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: var(--clr-amber-400);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .contact-title svg {
      width: 14px;
      height: 14px;
    }

    .contact-reporter-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--clr-white);
      margin-bottom: 0.5rem;
    }

    .contact-detail-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--clr-gray-300);
      margin-bottom: 0.4rem;
    }

    .contact-detail-row svg {
      width: 14px;
      height: 14px;
      color: var(--clr-teal-400);
    }

    .contact-actions {
      display: flex;
      gap: 0.75rem;
      margin-top: 1.25rem;
    }

    .btn-contact {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.65rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.25s ease;
    }

    .btn-contact-email {
      background: linear-gradient(135deg, var(--clr-teal-600), var(--clr-teal-500));
      color: var(--clr-white);
      border: none;
      box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
    }

    .btn-contact-email:hover {
      box-shadow: 0 6px 16px rgba(13, 148, 136, 0.3);
      filter: brightness(1.1);
      transform: translateY(-1px);
    }

    .btn-contact-phone {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: var(--clr-gray-200);
    }

    .btn-contact-phone:hover {
      background: rgba(255, 255, 255, 0.12);
      color: var(--clr-white);
      transform: translateY(-1px);
    }

    /* Responsive adjusts */
    @media (max-width: 820px) {
      .detail-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
      }

      .detail-photo-wrapper {
        height: 280px;
      }
    }
  </style>
</head>

<body>

  <!-- Header Navigation -->
  <header>
    <div class="brand-logo-container">
      <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="32" r="32" fill="url(#lg2)" />
        <circle cx="28" cy="27" r="10" stroke="white" stroke-width="3" fill="none" />
        <line x1="35.5" y1="34.5" x2="45" y2="44" stroke="white" stroke-width="3" stroke-linecap="round" />
        <defs>
          <linearGradient id="lg2" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
            <stop stop-color="#0D9488" />
            <stop offset="1" stop-color="#F59E0B" />
          </linearGradient>
        </defs>
      </svg>
      <span class="brand-title">Campus Lost &amp; Found</span>
    </div>

    <div class="user-menu">
      <a href="profile.php" class="header-avatar" aria-label="Edit Profile">
        <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
          <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Picture"
            class="header-avatar-img" />
        <?php else: ?>
          <div class="header-avatar-fallback"><?php echo htmlspecialchars(substr($user['name'], 0, 1)); ?></div>
        <?php endif; ?>
      </a>
      <div class="user-info-text">
        <a href="profile.php" class="user-welcome-name"><?php echo htmlspecialchars($user['name']); ?></a>
        <span class="user-welcome-email"><?php echo htmlspecialchars($user['email']); ?></span>
      </div>
      <a href="dashboard.php?action=logout" class="btn-logout">Logout</a>
    </div>
  </header>

  <!-- Main Body Content -->
  <main>

    <!-- Back Button Link -->
    <div class="btn-back-container">
      <a href="dashboard.php" class="btn-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to Dashboard
      </a>
    </div>

    <!-- Details Grid -->
    <section class="detail-grid">

      <!-- Left Panel: Photo and Badges -->
      <div class="detail-photo-card">
        <span class="category-badge"><?php echo htmlspecialchars($category); ?></span>
        <div class="detail-badge-group">
          <?php
          $badgeClass = '';
          $statusLower = strtolower($status);
          if ($statusLower === 'lost') {
            $badgeClass = 'status-lost';
          } elseif ($statusLower === 'found') {
            $badgeClass = 'status-found';
          } else {
            $badgeClass = 'status-returned';
          }
          ?>
          <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
        </div>

        <div class="detail-photo-wrapper">
          <?php
          $realPhotoPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $photoPath);
          if (!empty($photoPath) && file_exists($realPhotoPath)):
            ?>
            <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($itemName); ?>" />
          <?php else: ?>
            <!-- Fallback Category SVG Icon -->
            <svg class="detail-photo-fallback-icon" width="96" height="96" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <?php if ($category === 'Electronics'): ?>
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
              <?php elseif ($category === 'Books & Stationery'): ?>
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              <?php elseif ($category === 'Keys & Cards'): ?>
                <path d="M21 2h-6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z" />
                <path d="M3 10h10M3 14h10M7 6v12" />
              <?php elseif ($category === 'Clothing & Accessories'): ?>
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                <line x1="7" y1="7" x2="7.01" y2="7" />
              <?php else: ?>
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              <?php endif; ?>
            </svg>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Panel: Full Details Specs -->
      <div class="detail-info-card">

        <div class="item-title-section">
          <span class="item-detail-type"><?php echo $isLost ? 'Lost Item Report' : 'Found Item Report'; ?></span>
          <h2 class="item-detail-title"><?php echo htmlspecialchars($itemName); ?></h2>
        </div>

        <div class="specs-grid">

          <!-- Category -->
          <div class="spec-item">
            <div class="spec-icon-wrapper">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="9" />
                <rect x="14" y="3" width="7" height="5" />
                <rect x="14" y="12" width="7" height="9" />
                <rect x="3" y="16" width="7" height="5" />
              </svg>
            </div>
            <div class="spec-details">
              <span class="spec-label">Category</span>
              <span class="spec-value"><?php echo htmlspecialchars($category); ?></span>
            </div>
          </div>

          <!-- Description -->
          <div class="spec-item">
            <div class="spec-icon-wrapper" style="align-self: flex-start; margin-top: 2px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
                <polyline points="10 9 9 9 8 9" />
              </svg>
            </div>
            <div class="spec-details">
              <span class="spec-label">Description</span>
              <span class="spec-value"
                style="white-space: pre-wrap;"><?php echo htmlspecialchars($description); ?></span>
            </div>
          </div>

          <!-- Location -->
          <div class="spec-item">
            <div class="spec-icon-wrapper">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="spec-details">
              <span class="spec-label"><?php echo $isLost ? 'Last Seen Location' : 'Pickup Location'; ?></span>
              <span class="spec-value"><?php echo htmlspecialchars($location); ?></span>
            </div>
          </div>

          <!-- Date -->
          <div class="spec-item">
            <div class="spec-icon-wrapper">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
            </div>
            <div class="spec-details">
              <span class="spec-label"><?php echo $isLost ? 'Date Lost' : 'Date Found'; ?></span>
              <span class="spec-value"><?php echo htmlspecialchars(date('F d, Y', strtotime($dateReported))); ?></span>
            </div>
          </div>

        </div>

        <!-- Reporter/Finder Contact Info Card -->
        <div class="contact-info-card">
          <span class="contact-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            Contact Information
          </span>
          <h3 class="contact-reporter-name"><?php echo htmlspecialchars($contactName); ?></h3>

          <div class="contact-detail-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg>
            <span><?php echo htmlspecialchars($contactEmail); ?></span>
          </div>

          <?php if (!empty($contactPhone)): ?>
            <div class="contact-detail-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path
                  d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <span><?php echo htmlspecialchars($contactPhone); ?></span>
            </div>
          <?php endif; ?>

          <div class="contact-actions">
            <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>?subject=Inquiry%20regarding%20<?php echo urlencode($itemName); ?>"
              class="btn-contact btn-contact-email">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              Email Contact
            </a>
            <?php if (!empty($contactPhone)): ?>
              <a href="tel:<?php echo htmlspecialchars($contactPhone); ?>" class="btn-contact btn-contact-phone">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round">
                  <path
                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
                Call Contact
              </a>
            <?php endif; ?>
          </div>

        </div>

      </div>

    </section>

  </main>

</body>

</html>
