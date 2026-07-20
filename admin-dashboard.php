<?php
// admin-dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

if (!$user) {
    header('Location: login.php?action=logout');
    exit;
}

if (($user['role'] ?? 'user') !== 'admin') {
  header('Location: dashboard.php');
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'stats' => getAdminDashboardStats(),
        'activities' => getRecentAdminActivity(10),
        'refreshed_at' => date('c')
    ]);
    exit;
}

$stats = getAdminDashboardStats();
$activities = getRecentAdminActivity(10);
$refreshedAt = date('M d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
      --clr-emerald-500: #10b981;
      --clr-emerald-400: #34d399;
      --clr-red-500: #ef4444;
      --clr-white: #ffffff;
      --clr-gray-100: #f3f4f6;
      --clr-gray-200: #e5e7eb;
      --clr-gray-300: #d1d5db;
      --clr-gray-400: #9ca3af;
      --clr-gray-500: #6b7280;
      --clr-gray-700: #374151;
      --clr-gray-800: #1f2937;
      --clr-gray-900: #111827;
      --ff-base: 'Inter', system-ui, sans-serif;
      --radius-md: 14px;
      --radius-lg: 22px;
      --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.18), 0 8px 24px rgba(0, 0, 0, 0.10);
    }

    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: var(--ff-base);
      background: radial-gradient(circle at top, rgba(13, 148, 136, 0.18), transparent 30%), linear-gradient(180deg, #101827 0%, #0c1420 100%);
      color: var(--clr-white);
      min-height: 100vh;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1.15rem 1.8rem;
      background: rgba(8, 15, 28, 0.78);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(14px);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      text-decoration: none;
      color: inherit;
      min-width: 0;
    }

    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, var(--clr-teal-600), var(--clr-amber-400));
      box-shadow: 0 8px 24px rgba(13, 148, 136, 0.22);
      flex-shrink: 0;
    }

    .brand-title {
      display: block;
      font-weight: 800;
      font-size: 1.15rem;
      line-height: 1.1;
    }

    .brand-subtitle {
      display: block;
      font-size: 0.78rem;
      color: var(--clr-gray-400);
      margin-top: 0.15rem;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .refresh-pill, .pending-pill, .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.62rem 0.9rem;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: none;
      white-space: nowrap;
    }

    .refresh-pill {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: var(--clr-gray-200);
    }

    .pending-pill {
      background: rgba(251, 191, 36, 0.12);
      border: 1px solid rgba(251, 191, 36, 0.22);
      color: #fbbf24;
    }

    .btn-back {
      background: linear-gradient(135deg, var(--clr-teal-600), var(--clr-teal-500));
      color: var(--clr-white);
      box-shadow: 0 4px 12px rgba(13, 148, 136, 0.22);
    }

    main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1.5rem 3rem;
    }

    .hero {
      display: grid;
      grid-template-columns: 1.4fr auto;
      gap: 1rem;
      align-items: end;
      margin-bottom: 1.5rem;
    }

    .hero-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      backdrop-filter: blur(12px);
      padding: 1.4rem 1.5rem;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.32rem 0.7rem;
      border-radius: 999px;
      background: rgba(13, 148, 136, 0.12);
      border: 1px solid rgba(13, 148, 136, 0.22);
      color: var(--clr-teal-300);
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 0.85rem;
    }

    .hero h1 {
      margin: 0 0 0.45rem;
      font-size: clamp(1.7rem, 3vw, 2.4rem);
      line-height: 1.1;
    }

    .hero p {
      margin: 0;
      color: var(--clr-gray-400);
      max-width: 56rem;
      line-height: 1.6;
    }

    .stat-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 1rem;
      margin: 1.2rem 0 1.5rem;
    }

    .stat-card {
      position: relative;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      box-shadow: var(--shadow-lg);
      min-height: 145px;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      inset: 0 auto auto 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--clr-teal-500), var(--clr-amber-400));
    }

    .stat-card.lost::before { background: linear-gradient(90deg, #ef4444, #fb7185); }
    .stat-card.found::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .stat-card.claimed::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .stat-card.returned::before { background: linear-gradient(90deg, #06b6d4, #5eead4); }

    .stat-label {
      display: block;
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--clr-gray-400);
      margin-bottom: 0.8rem;
    }

    .stat-value {
      font-size: 2.4rem;
      line-height: 1;
      font-weight: 800;
      margin-bottom: 0.65rem;
    }

    .stat-note {
      color: var(--clr-gray-400);
      font-size: 0.85rem;
      line-height: 1.5;
    }

    .mini-note {
      margin-top: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.78rem;
      color: var(--clr-gray-300);
      padding: 0.35rem 0.55rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
    }

    .activity-wrap {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-top: 1.1rem;
    }

    .panel {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      backdrop-filter: blur(12px);
      overflow: hidden;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 1rem;
      padding: 1.25rem 1.35rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .panel-title {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 800;
    }

    .panel-subtitle {
      margin: 0.3rem 0 0;
      color: var(--clr-gray-400);
      font-size: 0.9rem;
    }

    .activity-feed {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .activity-item {
      display: grid;
      grid-template-columns: 44px 1fr auto;
      gap: 0.9rem;
      align-items: start;
      padding: 1rem 1.35rem;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      text-decoration: none;
      color: inherit;
      transition: background 0.2s ease, transform 0.2s ease;
    }

    .activity-item:first-child { border-top: 0; }
    .activity-item:hover {
      background: rgba(255, 255, 255, 0.035);
      transform: translateX(2px);
    }

    .activity-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
    }

    .activity-icon.lost { background: rgba(239, 68, 68, 0.14); color: #f87171; }
    .activity-icon.found { background: rgba(16, 185, 129, 0.14); color: #34d399; }
    .activity-icon.claim { background: rgba(245, 158, 11, 0.14); color: #fbbf24; }

    .activity-main h3 {
      margin: 0 0 0.25rem;
      font-size: 0.98rem;
      font-weight: 700;
    }

    .activity-main p {
      margin: 0;
      color: var(--clr-gray-400);
      font-size: 0.86rem;
      line-height: 1.55;
    }

    .activity-meta {
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.45rem;
      flex-shrink: 0;
    }

    .activity-time {
      color: var(--clr-gray-500);
      font-size: 0.78rem;
      white-space: nowrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.32rem 0.6rem;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      white-space: nowrap;
    }

    .badge.lost { background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
    .badge.found { background: rgba(16, 185, 129, 0.12); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
    .badge.pending { background: rgba(245, 158, 11, 0.12); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
    .badge.approved { background: rgba(16, 185, 129, 0.12); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
    .badge.rejected { background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
    .badge.returned { background: rgba(6, 182, 212, 0.12); color: #5eead4; border: 1px solid rgba(6, 182, 212, 0.2); }

    .empty-feed {
      padding: 2rem 1.35rem;
      color: var(--clr-gray-500);
      text-align: center;
    }

    .empty-feed strong {
      display: block;
      color: var(--clr-white);
      margin-bottom: 0.4rem;
    }

    @media (max-width: 1050px) {
      .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .hero { grid-template-columns: 1fr; }
    }

    @media (max-width: 720px) {
      header { padding: 1rem 1rem; align-items: flex-start; flex-direction: column; }
      .header-actions { width: 100%; justify-content: space-between; }
      main { padding: 1.1rem 0.9rem 2rem; }
      .stat-grid { grid-template-columns: 1fr; }
      .activity-item { grid-template-columns: 44px 1fr; }
      .activity-meta { grid-column: 2; align-items: flex-start; text-align: left; }
    }
  </style>
</head>
<body>
  <header>
    <a class="brand" href="admin-dashboard.php">
      <div class="brand-mark">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </div>
      <div>
        <span class="brand-title">Admin Dashboard</span>
        <span class="brand-subtitle">Campus Lost &amp; Found management</span>
      </div>
      <div><span class="brand-title">Admin Dashboard</span><span class="brand-subtitle">Campus Lost &amp; Found management</span></div>
    </a>
    <div class="actions">
      <span class="pill warn">Pending claims: <strong id="pendingClaimsHeader"><?php echo (int)$stats['pending_claims']; ?></strong></span>
      <span class="pill">Auto refresh: 60s</span>
      <a class="btn" href="admin-reports.php">Manage reports</a>
      <a class="btn" href="admin-audit-logs.php">Audit log</a>
      <a class="btn" href="dashboard.php" style="background:rgba(255,255,255,.06);box-shadow:none;border:1px solid rgba(255,255,255,.12);">Student dashboard</a>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="hero-card">
        <span class="eyebrow">Admin Overview</span>
        <h1>Dashboard Page Design</h1>
        <p>This view tracks lost reports, found reports, claims, and returned items in one place. The activity feed below is refreshed automatically so the figures stay current without reloading the page.</p>
      </div>
      <div class="hero-card" style="min-width: 240px;">
        <span class="eyebrow">Current Snapshot</span>
        <h1 style="margin-bottom: 0.35rem; font-size: 2rem;" id="refreshClock"><?php echo htmlspecialchars($refreshedAt); ?></h1>
        <p>Last updated from the database</p>
      </div>
    </section>

    <section class="stat-grid" aria-label="Dashboard summary cards">
      <article class="stat-card lost">
        <span class="stat-label">Lost Items</span>
        <div class="stat-value" id="lostCount"><?php echo (int)$stats['lost']; ?></div>
        <div class="stat-note">Items currently marked as lost in the database.</div>
        <div class="mini-note">Live from <strong>lost_items</strong></div>
      </article>

      <article class="stat-card found">
        <span class="stat-label">Found Items</span>
        <div class="stat-value" id="foundCount"><?php echo (int)$stats['found']; ?></div>
        <div class="stat-note">Items reported as found and ready for claims.</div>
        <div class="mini-note">Live from <strong>found_items</strong></div>
      </article>

      <article class="stat-card claimed">
        <span class="stat-label">Claimed Items</span>
        <div class="stat-value" id="claimedCount"><?php echo (int)$stats['claimed']; ?></div>
        <div class="stat-note">Distinct found items that already have at least one claim.</div>
        <div class="mini-note">Pending claims: <strong id="pendingClaimsCard"><?php echo (int)$stats['pending_claims']; ?></strong></div>
      </article>

      <article class="stat-card returned">
        <span class="stat-label">Returned Items</span>
        <div class="stat-value" id="returnedCount"><?php echo (int)$stats['returned']; ?></div>
        <div class="stat-note">Reports marked returned across lost and found records.</div>
        <div class="mini-note">Status: <strong>Returned</strong></div>
      </article>
    </section>

    <section class="panel" aria-labelledby="activityTitle">
      <div class="panel-header">
        <div>
          <h2 class="panel-title" id="activityTitle">Recent Activity Feed</h2>
          <p class="panel-subtitle">Latest 10 events from reports and claims.</p>
        </div>
        <a class="btn-back" href="dashboard.php" style="padding: 0.55rem 0.85rem;">Open student dashboard</a>
      </div>

      <div id="activityFeed">
        <?php if (!empty($activities)): ?>
          <?php foreach ($activities as $activity): ?>
            <a class="activity-item" href="<?php echo htmlspecialchars($activity['link']); ?>">
              <div class="activity-icon <?php echo htmlspecialchars($activity['kind']); ?>">
                <?php if ($activity['kind'] === 'lost'): ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php elseif ($activity['kind'] === 'found'): ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php else: ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php endif; ?>
              </div>
              <div class="activity-main">
                <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                <p><?php echo htmlspecialchars($activity['details']); ?> by <?php echo htmlspecialchars($activity['actor_name']); ?>.</p>
              </div>
              <div class="activity-meta">
                <span class="badge <?php echo htmlspecialchars($activity['badge_class']); ?>"><?php echo htmlspecialchars($activity['status']); ?></span>
                <span class="activity-time"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($activity['activity_time']))); ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-feed">
            <strong>No activity yet.</strong>
            Recent reports and claim events will appear here as soon as students submit them.
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    const refreshClock = document.getElementById('refreshClock');
    const lostCount = document.getElementById('lostCount');
    const foundCount = document.getElementById('foundCount');
    const claimedCount = document.getElementById('claimedCount');
    const returnedCount = document.getElementById('returnedCount');
    const pendingClaimsHeader = document.getElementById('pendingClaimsHeader');
    const pendingClaimsCard = document.getElementById('pendingClaimsCard');
    const activityFeed = document.getElementById('activityFeed');

    function escapeHtml(value) {
      return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function formatDate(value) {
      const date = new Date(value);
      return date.toLocaleString([], { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function activityIcon(kind) {
      if (kind === 'lost') {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
      }
      if (kind === 'found') {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
      }
      return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    }

    function renderActivities(items) {
      if (!items.length) {
        activityFeed.innerHTML = '<div class="empty-feed"><strong>No activity yet.</strong> Recent reports and claim events will appear here as soon as students submit them.</div>';
        return;
      }

      activityFeed.innerHTML = items.map((item) => {
        const kind = item.kind || 'claim';
        const badgeClass = item.badge_class || 'pending';
        return `
          <a class="activity-item" href="${escapeHtml(item.link)}">
            <div class="activity-icon ${escapeHtml(kind)}">${activityIcon(kind)}</div>
            <div class="activity-main">
              <h3>${escapeHtml(item.title)}</h3>
              <p>${escapeHtml(item.details)} by ${escapeHtml(item.actor_name)}.</p>
            </div>
            <div class="activity-meta">
              <span class="badge ${escapeHtml(badgeClass)}">${escapeHtml(item.status)}</span>
              <span class="activity-time">${formatDate(item.activity_time)}</span>
            </div>
          </a>
        `;
      }).join('');
    }

    async function refreshAdminDashboard() {
      try {
        const response = await fetch('admin-dashboard.php?format=json', { cache: 'no-store' });
        if (!response.ok) {
          return;
        }
        const data = await response.json();

        lostCount.textContent = data.stats.lost ?? 0;
        foundCount.textContent = data.stats.found ?? 0;
        claimedCount.textContent = data.stats.claimed ?? 0;
        returnedCount.textContent = data.stats.returned ?? 0;
        pendingClaimsHeader.textContent = data.stats.pending_claims ?? 0;
        pendingClaimsCard.textContent = data.stats.pending_claims ?? 0;
        refreshClock.textContent = formatDate(data.refreshed_at);
        renderActivities(data.activities || []);
      } catch (error) {
        console.error('Dashboard refresh failed:', error);
      }
    }

    setInterval(refreshAdminDashboard, 60000);
  </script>
</body>
</html>
