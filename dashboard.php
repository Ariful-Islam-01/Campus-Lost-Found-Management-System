<?php
// dashboard.php
session_start();

// Block access if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$lostItems = getLostItems();
$foundItems = getFoundItems();

// Fallback if user session is invalid
if (!$user) {
    header('Location: login.php?action=logout');
    exit;
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Campus Lost & Found</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Inline Vanilla CSS for maximum flexibility and clean presentation -->
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
      --clr-white:   #ffffff;
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

    *, *::before, *::after {
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

    .welcome-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-lg);
      padding: 3rem;
      box-shadow: var(--shadow-lg), inset 0 1px 0 rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(12px);
      text-align: center;
      margin-bottom: 2rem;
    }

    .welcome-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(13, 148, 136, 0.2), rgba(245, 158, 11, 0.15));
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      color: var(--clr-teal-400);
      box-shadow: 0 8px 24px rgba(13, 148, 136, 0.15);
    }

    .welcome-title {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
    }

    .welcome-tagline {
      color: var(--clr-gray-400);
      font-size: 1rem;
      max-width: 500px;
      margin: 0 auto 2.5rem;
    }

    /* Dashboard Actions Grid */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .action-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      padding: 2rem;
      text-align: left;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
    }

    .action-card:hover {
      background: rgba(255, 255, 255, 0.07);
      border-color: var(--clr-teal-500);
      transform: translateY(-4px);
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.25rem;
    }

    .icon-lost {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
    }

    .icon-found {
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
    }

    .action-title {
      font-size: 1.25rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .action-desc {
      color: var(--clr-gray-400);
      font-size: 0.875rem;
      line-height: 1.5;
    }

    /* Reports Section Styling */
    .reports-section {
      margin-top: 4rem;
      text-align: left;
    }

    .reports-title {
      font-size: 1.5rem;
      font-weight: 800;
      margin-bottom: 1.75rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 0.75rem;
      background: linear-gradient(135deg, #fff 50%, var(--clr-teal-300));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .reports-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .report-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      backdrop-filter: blur(12px);
    }

    .report-card:hover {
      transform: translateY(-4px);
      border-color: var(--clr-teal-500);
      background: rgba(255, 255, 255, 0.04);
      box-shadow: 0 10px 30px rgba(13, 148, 136, 0.15);
    }

    .report-thumbnail-container {
      width: 100%;
      height: 180px;
      background: rgba(255, 255, 255, 0.01);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .report-thumbnail {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .report-card:hover .report-thumbnail {
      transform: scale(1.05);
    }

    .report-badge {
      position: absolute;
      top: 12px;
      right: 12px;
      padding: 0.3rem 0.75rem;
      border-radius: 99px;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      background: rgba(239, 68, 68, 0.9);
      color: white;
      border: 1px solid rgba(239, 68, 68, 0.4);
      z-index: 2;
    }

    .report-badge.badge-found {
      background: rgba(16, 185, 129, 0.9);
      border-color: rgba(16, 185, 129, 0.4);
    }

    .report-category-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      padding: 0.3rem 0.75rem;
      border-radius: 99px;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      background: rgba(13, 148, 136, 0.8);
      color: white;
      backdrop-filter: blur(4px);
      z-index: 2;
    }

    .report-fallback-icon {
      color: var(--clr-teal-500);
      opacity: 0.4;
    }

    .report-details {
      padding: 1.25rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .report-header-info {
      margin-bottom: 1rem;
    }

    .report-item-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--clr-white);
      margin-bottom: 0.4rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .report-description {
      font-size: 0.85rem;
      color: var(--clr-gray-400);
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
      height: 3.85em; /* approximate height for 3 lines */
    }

    .report-meta-list {
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      padding-top: 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
    }

    .report-meta-item {
      font-size: 0.78rem;
      color: var(--clr-gray-400);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .report-meta-item svg {
      width: 14px;
      height: 14px;
      color: var(--clr-teal-400);
      flex-shrink: 0;
    }

    .report-reporter-info {
      font-weight: 600;
      color: var(--clr-teal-300);
    }

    .no-reports-msg {
      grid-column: 1 / -1;
      text-align: center;
      padding: 4rem 2rem;
      background: rgba(255, 255, 255, 0.01);
      border: 1px dashed rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      color: var(--clr-gray-500);
      font-size: 0.9rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .actions-grid {
        grid-template-columns: 1fr;
      }
      .welcome-card {
        padding: 2rem;
      }
      .reports-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <!-- Header Navigation -->
  <header>
    <div class="brand-logo-container">
      <svg width="32" height="32" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="32" cy="32" r="32" fill="url(#lg2)"/>
        <circle cx="28" cy="27" r="10" stroke="white" stroke-width="3" fill="none"/>
        <line x1="35.5" y1="34.5" x2="45" y2="44" stroke="white" stroke-width="3" stroke-linecap="round"/>
        <defs>
          <linearGradient id="lg2" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
            <stop stop-color="#0D9488"/>
            <stop offset="1" stop-color="#F59E0B"/>
          </linearGradient>
        </defs>
      </svg>
      <span class="brand-title">Campus Lost &amp; Found</span>
    </div>

    <div class="user-menu">
      <a href="profile.php" class="header-avatar" aria-label="Edit Profile">
        <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
          <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Picture" class="header-avatar-img" />
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
    <div class="welcome-card">
      <div class="welcome-icon">
        <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
          <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Picture" style="width:100%; height:100%; border-radius:50%; object-fit:cover;" />
        <?php else: ?>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?php endif; ?>
      </div>
      <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
      <p class="welcome-tagline">You are logged into the Student Dashboard. What would you like to do today?</p>
      
      <!-- Actions Grid -->
      <div class="actions-grid">
        <a href="report-lost.php" class="action-card">
          <div class="action-icon icon-lost">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <h3 class="action-title">Report Lost Item</h3>
          <p class="action-desc">Lost something? Post details about your missing item and campus location to search the directory.</p>
        </a>

        <a href="report-found.php" class="action-card">
          <div class="action-icon icon-found">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3 class="action-title">Report Found Item</h3>
          <p class="action-desc">Found someone's belongings? Report item details and specify the pickup location on campus.</p>
        </a>

        <a href="profile.php" class="action-card">
          <div class="action-icon icon-found" style="background: rgba(245,158,11,0.15); color: var(--clr-amber-400);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </div>
          <h3 class="action-title">Manage Profile</h3>
          <p class="action-desc">Keep your details up to date. Edit your name, contact phone number, or upload a custom avatar.</p>
        </a>

        <a href="listings.php" class="action-card">
          <div class="action-icon icon-found" style="background: rgba(13, 148, 136, 0.15); color: var(--clr-teal-400);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
          </div>
          <h3 class="action-title">Browse Listings</h3>
          <p class="action-desc">View all lost and found reports. Search, filter by category, and browse items using page navigation.</p>
        </a>
      </div>
    </div>

    <!-- Reports Section -->
    <div class="reports-section">
      <h2 class="reports-title">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Active Lost Reports
      </h2>
      <div class="reports-grid">
        <?php if (!empty($lostItems)): ?>
          <?php foreach ($lostItems as $item): ?>
            <div class="report-card">
              <div class="report-thumbnail-container">
                <span class="report-badge"><?php echo htmlspecialchars($item['status']); ?></span>
                <span class="report-category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                <?php 
                $realPhotoPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item['photo_path']);
                if (!empty($item['photo_path']) && file_exists($realPhotoPath)): 
                ?>
                  <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="report-thumbnail" loading="lazy" />
                <?php else: ?>
                  <!-- Fallback Icon Placeholder based on Category -->
                  <svg class="report-fallback-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($item['category'] === 'Electronics'): ?>
                      <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                    <?php elseif ($item['category'] === 'Books & Stationery'): ?>
                      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    <?php elseif ($item['category'] === 'Keys & Cards'): ?>
                      <path d="M21 2h-6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 10h10M3 14h10M7 6v12"/>
                    <?php elseif ($item['category'] === 'Clothing & Accessories'): ?>
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                    <?php else: ?>
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    <?php endif; ?>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="report-details">
                <div class="report-header-info">
                  <h3 class="report-item-name" title="<?php echo htmlspecialchars($item['item_name']); ?>"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                  <p class="report-description"><?php echo htmlspecialchars($item['description']); ?></p>
                </div>
                <div class="report-meta-list">
                  <div class="report-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Last seen: <strong><?php echo htmlspecialchars($item['last_seen_location']); ?></strong></span>
                  </div>
                  <div class="report-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Date Lost: <strong><?php echo htmlspecialchars(date('M d, Y', strtotime($item['date_lost']))); ?></strong></span>
                  </div>
                  <div class="report-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>By: <span class="report-reporter-info"><?php echo htmlspecialchars($item['reporter_name']); ?></span> (<?php echo htmlspecialchars($item['reporter_email']); ?>)</span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-reports-msg">
            <svg style="margin: 0 auto 1rem; display: block; color: var(--clr-gray-600);" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            No active lost reports found. If you lost something, report it to show here!
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Found Reports Section -->
    <div class="reports-section" style="margin-top: 3rem; margin-bottom: 2rem;">
      <h2 class="reports-title" style="color: var(--clr-emerald-400);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Active Found Reports
      </h2>
      <div class="reports-grid">
        <?php if (!empty($foundItems)): ?>
          <?php foreach ($foundItems as $item): ?>
            <div class="report-card" style="border-color: rgba(16, 185, 129, 0.15);">
              <div class="report-thumbnail-container" style="border-bottom-color: rgba(16, 185, 129, 0.1);">
                <span class="report-badge badge-found"><?php echo htmlspecialchars($item['status']); ?></span>
                <span class="report-category-badge" style="background: rgba(16, 185, 129, 0.8);"><?php echo htmlspecialchars($item['category']); ?></span>
                <?php 
                $realPhotoPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item['photo_path']);
                if (!empty($item['photo_path']) && file_exists($realPhotoPath)): 
                ?>
                  <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="report-thumbnail" loading="lazy" />
                <?php else: ?>
                  <!-- Fallback Icon Placeholder based on Category -->
                  <svg class="report-fallback-icon" style="color: var(--clr-emerald-500);" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($item['category'] === 'Electronics'): ?>
                      <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                    <?php elseif ($item['category'] === 'Books & Stationery'): ?>
                      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    <?php elseif ($item['category'] === 'Keys & Cards'): ?>
                      <path d="M21 2h-6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 10h10M3 14h10M7 6v12"/>
                    <?php elseif ($item['category'] === 'Clothing & Accessories'): ?>
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                    <?php else: ?>
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    <?php endif; ?>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="report-details">
                <div class="report-header-info">
                  <h3 class="report-item-name" title="<?php echo htmlspecialchars($item['item_name']); ?>"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                  <p class="report-description"><?php echo htmlspecialchars($item['description']); ?></p>
                </div>
                <div class="report-meta-list">
                  <div class="report-meta-item">
                    <svg style="color: var(--clr-emerald-400);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Pickup: <strong><?php echo htmlspecialchars($item['pickup_location']); ?></strong></span>
                  </div>
                  <div class="report-meta-item">
                    <svg style="color: var(--clr-emerald-400);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Date Found: <strong><?php echo htmlspecialchars(date('M d, Y', strtotime($item['created_at']))); ?></strong></span>
                  </div>
                  <div class="report-meta-item">
                    <svg style="color: var(--clr-emerald-400);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Finder: <span class="report-reporter-info" style="color: var(--clr-emerald-300);"><?php echo htmlspecialchars($item['finder_name']); ?></span> (<?php echo htmlspecialchars($item['finder_email']); ?>)</span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-reports-msg">
            <svg style="margin: 0 auto 1rem; display: block; color: var(--clr-gray-600);" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            No active found reports found. If you found something, report it to show here!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

</body>
</html>
