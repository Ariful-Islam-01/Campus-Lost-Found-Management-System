<?php
// listings.php
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

// Get query parameters for filtering and pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$type = isset($_GET['type']) ? trim($_GET['type']) : 'All';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate type parameter
if (!in_array($type, ['All', 'Lost', 'Found'])) {
    $type = 'All';
}

// Define allowed categories
$allowedCategories = ['Electronics', 'Books & Stationery', 'Keys & Cards', 'Clothing & Accessories', 'Others'];
if (!empty($category) && !in_array($category, $allowedCategories)) {
    $category = '';
}

// Pagination setup
$limit = 6; // 6 items per page fits a 3-column layout nicely
$offset = ($page - 1) * $limit;

// Fetch data
$totalItems = getTotalItemsCount($type, $category, $search);
$totalPages = ceil($totalItems / $limit);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}
$items = getPaginatedItems($type, $category, $search, $limit, $offset);

// Build query string helper for pagination links
function buildQueryString($newPage, $currentType, $currentCategory, $currentSearch) {
    $params = ['page' => $newPage];
    if ($currentType !== 'All') {
        $params['type'] = $currentType;
    }
    if (!empty($currentCategory)) {
        $params['category'] = $currentCategory;
    }
    if (!empty($currentSearch)) {
        $params['search'] = $currentSearch;
    }
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Listings | Campus Lost &amp; Found</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Inline CSS for consistency and design style matching dashboard.php -->
  <style>
    :root {
      --clr-teal-900: #042f2e;
      --clr-teal-800: #134e4a;
      --clr-teal-700: #0f766e;
      --clr-teal-600: #0d9488;
      --clr-teal-500: #14b8a6;
      --clr-teal-400: #2dd4bf;
      --clr-teal-300: #5eead4;
      --clr-amber-500: #f59e0b;
      --clr-amber-400: #fbbf24;
      --clr-emerald-300: #6ee7b7;
      --clr-emerald-400: #34d399;
      --clr-emerald-500: #10b981;
      --clr-white:   #ffffff;
      --clr-gray-100: #f3f4f6;
      --clr-gray-200: #e5e7eb;
      --clr-gray-300: #d1d5db;
      --clr-gray-400: #9ca3af;
      --clr-gray-500: #6b7280;
      --clr-gray-600: #4b5563;
      --clr-gray-800: #1f2937;
      --clr-gray-900: #111827;
      --ff-base: 'Inter', system-ui, sans-serif;
      --radius-sm: 8px;
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
      text-decoration: none;
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

    .btn-dashboard {
      padding: 0.55rem 1.15rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--clr-white);
      background: linear-gradient(135deg, var(--clr-teal-600) 0%, var(--clr-teal-500) 100%);
      border: none;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
      transition: all 0.25s ease;
    }

    .btn-dashboard:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(13, 148, 136, 0.4);
    }

    /* Main layout container */
    main {
      flex: 1;
      max-width: 1100px;
      width: 100%;
      margin: 2.5rem auto;
      padding: 0 2rem;
    }

    .page-header {
      margin-bottom: 2rem;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(135deg, #fff 40%, var(--clr-teal-300));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      color: var(--clr-gray-400);
      font-size: 0.95rem;
    }

    /* Filters Section */
    .filter-panel {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      padding: 1.5rem;
      margin-bottom: 2.5rem;
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .filter-form {
      display: grid;
      grid-template-columns: 2fr 1fr auto;
      gap: 1rem;
      align-items: end;
    }

    .field-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .field-label {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--clr-gray-300);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      color: var(--clr-gray-400);
      display: flex;
      align-items: center;
      pointer-events: none;
    }

    .input-icon svg {
      width: 18px;
      height: 18px;
    }

    .form-input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      font-family: var(--ff-base);
      font-size: 0.9rem;
      color: var(--clr-white);
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-sm);
      outline: none;
      transition: all 0.25s ease;
    }

    .form-input:focus {
      border-color: var(--clr-teal-500);
      background: rgba(255, 255, 255, 0.06);
      box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
    }

    .form-select {
      appearance: none;
      padding-right: 2.5rem;
      cursor: pointer;
    }

    .select-arrow {
      position: absolute;
      right: 14px;
      color: var(--clr-gray-400);
      pointer-events: none;
      display: flex;
      align-items: center;
    }

    .select-arrow svg {
      width: 16px;
      height: 16px;
    }

    .filter-actions {
      display: flex;
      gap: 0.75rem;
    }

    .btn-search {
      padding: 0.75rem 1.5rem;
      font-family: var(--ff-base);
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--clr-white);
      background: linear-gradient(135deg, var(--clr-teal-600) 0%, var(--clr-teal-500) 100%);
      border: none;
      border-radius: var(--radius-sm);
      cursor: pointer;
      transition: all 0.25s ease;
      box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
    }

    .btn-search:hover {
      box-shadow: 0 6px 16px rgba(13, 148, 136, 0.35);
      transform: translateY(-1px);
    }

    .btn-reset {
      padding: 0.75rem 1.25rem;
      font-family: var(--ff-base);
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--clr-gray-300);
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-sm);
      text-decoration: none;
      cursor: pointer;
      text-align: center;
      transition: all 0.25s ease;
    }

    .btn-reset:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.15);
      color: var(--clr-white);
    }

    /* Tabs Filter */
    .tabs-container {
      display: flex;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      margin-bottom: 2rem;
      gap: 1.5rem;
    }

    .tab-item {
      padding: 0.75rem 0.5rem;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--clr-gray-400);
      text-decoration: none;
      position: relative;
      transition: color 0.25s ease;
    }

    .tab-item:hover {
      color: var(--clr-white);
    }

    .tab-item.active {
      color: var(--clr-teal-400);
    }

    .tab-item.active::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--clr-teal-500);
      box-shadow: 0 0 8px var(--clr-teal-400);
    }

    /* Grid layout */
    .listings-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
      gap: 1.75rem;
      margin-bottom: 3.5rem;
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
      height: 190px;
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

    .report-details-top {
      margin-bottom: 1rem;
    }

    .report-item-name {
      font-size: 1.15rem;
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

    .btn-view-details {
      display: inline-block;
      padding: 0.65rem 1.25rem;
      margin-top: 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--clr-white);
      background: linear-gradient(135deg, var(--clr-teal-600) 0%, var(--clr-teal-500) 100%);
      border: none;
      border-radius: var(--radius-sm);
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      transition: all 0.25s ease;
      box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
    }

    .btn-view-details:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(13, 148, 136, 0.3);
      filter: brightness(1.1);
    }

    .btn-view-details:active {
      transform: translateY(0);
    }

    .no-reports-msg {
      grid-column: 1 / -1;
      text-align: center;
      padding: 5rem 2rem;
      background: rgba(255, 255, 255, 0.01);
      border: 1px dashed rgba(255, 255, 255, 0.06);
      border-radius: var(--radius-md);
      color: var(--clr-gray-500);
      font-size: 1rem;
    }

    /* Pagination controls */
    .pagination-container {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 2rem;
      margin-top: 1.5rem;
    }

    .pagination-btn {
      padding: 0.55rem 0.9rem;
      border-radius: var(--radius-sm);
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: var(--clr-gray-300);
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.25s ease;
      cursor: pointer;
    }

    .pagination-btn:hover:not(.disabled) {
      background: rgba(13, 148, 136, 0.1);
      border-color: var(--clr-teal-500);
      color: var(--clr-white);
    }

    .pagination-btn.active {
      background: linear-gradient(135deg, var(--clr-teal-600) 0%, var(--clr-teal-500) 100%);
      border-color: var(--clr-teal-500);
      color: var(--clr-white);
      box-shadow: 0 0 12px rgba(13, 148, 136, 0.3);
    }

    .pagination-btn.disabled {
      opacity: 0.35;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .filter-form {
        grid-template-columns: 1fr;
      }
      
      .filter-actions {
        width: 100%;
        margin-top: 0.5rem;
      }
      
      .btn-search, .btn-reset {
        flex: 1;
      }
      
      .listings-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <!-- Header Navigation -->
  <header>
    <a href="dashboard.php" class="brand-logo-container">
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
    </a>

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
      <a href="dashboard.php" class="btn-dashboard">Dashboard</a>
    </div>
  </header>

  <!-- Main Body Content -->
  <main>
    <div class="page-header">
      <h1 class="page-title">Browse Active Reports</h1>
      <p class="page-subtitle">Find or track items reported lost or found within the campus community.</p>
    </div>

    <!-- Filters Panel -->
    <div class="filter-panel">
      <form method="GET" action="" class="filter-form">
        <!-- Retain type filter in form -->
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>" />
        
        <!-- Search query -->
        <div class="field-group">
          <label class="field-label" for="search">Keyword Search</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <input type="text" id="search" name="search" class="form-input" placeholder="Search by name, description or location..." value="<?php echo htmlspecialchars($search); ?>" />
          </div>
        </div>

        <!-- Category Filter -->
        <div class="field-group">
          <label class="field-label" for="category">Category</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
            </span>
            <select id="category" name="category" class="form-input form-select">
              <option value="">All Categories</option>
              <?php foreach ($allowedCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="select-arrow">
              <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
          </div>
        </div>

        <!-- Filter Action Buttons -->
        <div class="filter-actions">
          <button type="submit" class="btn-search">Search</button>
          <a href="listings.php?type=<?php echo htmlspecialchars($type); ?>" class="btn-reset">Reset</a>
        </div>
      </form>
    </div>

    <!-- Type Switcher Tabs -->
    <div class="tabs-container">
      <a href="<?php echo buildQueryString(1, 'All', $category, $search); ?>" class="tab-item <?php echo ($type === 'All') ? 'active' : ''; ?>">
        All Items
      </a>
      <a href="<?php echo buildQueryString(1, 'Lost', $category, $search); ?>" class="tab-item <?php echo ($type === 'Lost') ? 'active' : ''; ?>">
        Lost Reports
      </a>
      <a href="<?php echo buildQueryString(1, 'Found', $category, $search); ?>" class="tab-item <?php echo ($type === 'Found') ? 'active' : ''; ?>">
        Found Reports
      </a>
    </div>

    <!-- Listings Grid -->
    <div class="listings-grid">
      <?php if (!empty($items)): ?>
        <?php foreach ($items as $item): ?>
          <div class="report-card" style="<?php echo ($item['type'] === 'Found') ? 'border-color: rgba(16, 185, 129, 0.15);' : ''; ?>">
            <div class="report-thumbnail-container" style="<?php echo ($item['type'] === 'Found') ? 'border-bottom-color: rgba(16, 185, 129, 0.1);' : ''; ?>">
              <!-- Status Badge -->
              <span class="report-badge <?php echo ($item['type'] === 'Found') ? 'badge-found' : ''; ?>">
                <?php echo htmlspecialchars($item['status']); ?>
              </span>
              
              <!-- Category Badge -->
              <span class="report-category-badge" style="<?php echo ($item['type'] === 'Found') ? 'background: rgba(16, 185, 129, 0.8);' : ''; ?>">
                <?php echo htmlspecialchars($item['category']); ?>
              </span>
              
              <!-- Image/Fallback -->
              <?php 
              $realPhotoPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item['photo_path']);
              if (!empty($item['photo_path']) && file_exists($realPhotoPath)): 
              ?>
                <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="report-thumbnail" loading="lazy" />
              <?php else: ?>
                <!-- Fallback SVG icons based on category -->
                <svg class="report-fallback-icon" style="<?php echo ($item['type'] === 'Found') ? 'color: var(--clr-emerald-500);' : ''; ?>" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
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

            <!-- Details -->
            <div class="report-details">
              <div class="report-details-top">
                <h3 class="report-item-name" title="<?php echo htmlspecialchars($item['item_name']); ?>">
                  <?php echo htmlspecialchars($item['item_name']); ?>
                </h3>
                <p class="report-description"><?php echo htmlspecialchars($item['description']); ?></p>
              </div>

              <!-- Metadata -->
              <div class="report-meta-list">
                <div class="report-meta-item">
                  <svg style="<?php echo ($item['type'] === 'Found') ? 'color: var(--clr-emerald-400);' : ''; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span>
                    <?php echo ($item['type'] === 'Found') ? 'Pickup:' : 'Last seen:'; ?>
                    <strong><?php echo htmlspecialchars($item['location']); ?></strong>
                  </span>
                </div>
                
                <div class="report-meta-item">
                  <svg style="<?php echo ($item['type'] === 'Found') ? 'color: var(--clr-emerald-400);' : ''; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <span>
                    <?php echo ($item['type'] === 'Found') ? 'Date Found:' : 'Date Lost:'; ?>
                    <strong><?php echo htmlspecialchars(date('M d, Y', strtotime($item['item_date']))); ?></strong>
                  </span>
                </div>

                <div class="report-meta-item">
                  <svg style="<?php echo ($item['type'] === 'Found') ? 'color: var(--clr-emerald-400);' : ''; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <span>
                    By: <span class="report-reporter-info" style="<?php echo ($item['type'] === 'Found') ? 'color: var(--clr-emerald-300);' : ''; ?>"><?php echo htmlspecialchars($item['reporter_name']); ?></span> 
                    (<?php echo htmlspecialchars($item['reporter_email']); ?><?php echo !empty($item['reporter_phone']) ? ', ' . htmlspecialchars($item['reporter_phone']) : ''; ?>)
                  </span>
                </div>
              </div>

              <!-- View Details Button -->
              <a href="item-detail.php?id=<?php echo $item['id']; ?>&type=<?php echo strtolower($item['type']); ?>" class="btn-view-details" style="<?php echo ($item['type'] === 'Found') ? 'background: linear-gradient(135deg, #059669 0%, #10b981 100%);' : ''; ?>">
                View Details
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-reports-msg">
          <svg style="margin: 0 auto 1.25rem; display: block; color: var(--clr-gray-600);" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          No active reports match your selection.
        </div>
      <?php endif; ?>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-container">
        <!-- Previous Button -->
        <a href="<?php echo buildQueryString($page - 1, $type, $category, $search); ?>" class="pagination-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>" aria-label="Previous Page">
          &laquo; Prev
        </a>

        <!-- Page Numbers -->
        <?php 
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1) {
            echo '<a href="' . buildQueryString(1, $type, $category, $search) . '" class="pagination-btn">1</a>';
            if ($startPage > 2) {
                echo '<span style="color: var(--clr-gray-600); padding: 0 0.25rem;">...</span>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $activeClass = ($i === $page) ? 'active' : '';
            echo '<a href="' . buildQueryString($i, $type, $category, $search) . '" class="pagination-btn ' . $activeClass . '">' . $i . '</a>';
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<span style="color: var(--clr-gray-600); padding: 0 0.25rem;">...</span>';
            }
            echo '<a href="' . buildQueryString($totalPages, $type, $category, $search) . '" class="pagination-btn">' . $totalPages . '</a>';
        }
        ?>

        <!-- Next Button -->
        <a href="<?php echo buildQueryString($page + 1, $type, $category, $search); ?>" class="pagination-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>" aria-label="Next Page">
          Next &raquo;
        </a>
      </div>
    <?php endif; ?>
  </main>

</body>
</html>
