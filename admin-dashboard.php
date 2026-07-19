<?php
// admin-dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$user = getUserById($_SESSION['user_id']);
if (!$user) {
    header('Location: login.php?action=logout');
    exit;
}

if (!isAdminUser($user)) {
    header('Location: dashboard.php');
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
    :root{--c1:#042f2e;--c2:#0d9488;--c3:#2dd4bf;--c4:#fbbf24;--c5:#10b981;--c6:#ef4444;--w:#fff;--g1:#f3f4f6;--g2:#d1d5db;--g3:#9ca3af;--g4:#6b7280;--g5:#111827;--r1:14px;--r2:22px;--s:0 20px 60px rgba(0,0,0,.18),0 8px 24px rgba(0,0,0,.10);font-family:'Inter',system-ui,sans-serif;}
    *{box-sizing:border-box} body{margin:0;min-height:100vh;background:radial-gradient(circle at top, rgba(13,148,136,.20), transparent 30%),linear-gradient(180deg,#101827 0%,#0c1420 100%);color:var(--w)}
    header{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.5rem;background:rgba(8,15,28,.80);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08)}
    .brand{display:flex;gap:.8rem;align-items:center;text-decoration:none;color:inherit}.brand-mark{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--c2),var(--c4))}.brand-title{font-weight:800;font-size:1.1rem}.brand-subtitle{font-size:.78rem;color:var(--g3)}
    .actions{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;justify-content:flex-end}.pill,.btn{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .85rem;border-radius:999px;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap}.pill{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--g1)}.pill.warn{background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.22);color:var(--c4)}.btn{background:linear-gradient(135deg,var(--c2),var(--c3));color:var(--w);box-shadow:0 4px 12px rgba(13,148,136,.22)}
    main{max-width:1200px;margin:0 auto;padding:2rem 1.2rem 3rem}.hero{display:grid;grid-template-columns:1.4fr auto;gap:1rem;align-items:end}.card,.panel{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:var(--r2);box-shadow:var(--s);backdrop-filter:blur(12px)}.hero .card{padding:1.35rem 1.45rem}.eyebrow{display:inline-flex;padding:.3rem .65rem;border-radius:999px;background:rgba(13,148,136,.12);border:1px solid rgba(13,148,136,.22);color:#5eead4;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.8rem}.hero h1{margin:.1rem 0 .45rem;font-size:clamp(1.8rem,3vw,2.5rem);line-height:1.08}.hero p{margin:0;color:var(--g3);line-height:1.6}.top-row{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin:1.4rem 0 1rem}.top-row h2{margin:0;font-size:1.1rem}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1.1rem 0 1.4rem}.stat{position:relative;overflow:hidden;padding:1.1rem 1.15rem;min-height:145px}.stat::before{content:'';position:absolute;inset:0 auto auto 0;width:100%;height:4px;background:linear-gradient(90deg,var(--c2),var(--c4))}.stat.lost::before{background:linear-gradient(90deg,#ef4444,#fb7185)}.stat.found::before{background:linear-gradient(90deg,#10b981,#34d399)}.stat.claimed::before{background:linear-gradient(90deg,#f59e0b,#fbbf24)}.stat.returned::before{background:linear-gradient(90deg,#06b6d4,#5eead4)}.label{display:block;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--g3);margin-bottom:.85rem}.value{font-size:2.4rem;line-height:1;font-weight:800;margin-bottom:.55rem}.note{font-size:.85rem;color:var(--g3);line-height:1.5}.mini{margin-top:.85rem;display:inline-flex;gap:.35rem;align-items:center;padding:.32rem .55rem;border-radius:999px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);font-size:.78rem;color:var(--g2)}
    .panel{overflow:hidden}.panel-head{display:flex;justify-content:space-between;gap:1rem;align-items:end;padding:1.15rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.08)}.panel-title{margin:0;font-size:1.1rem}.panel-sub{margin:.25rem 0 0;color:var(--g3);font-size:.9rem}.feed{list-style:none;margin:0;padding:0}.feed li{display:grid;grid-template-columns:44px 1fr auto;gap:.9rem;align-items:start;padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.06);text-decoration:none;color:inherit}.feed li:first-child{border-top:0}.icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center}.icon.lost{background:rgba(239,68,68,.14);color:#f87171}.icon.found{background:rgba(16,185,129,.14);color:#34d399}.icon.claim{background:rgba(245,158,11,.14);color:#fbbf24}.activity h3{margin:0 0 .25rem;font-size:.98rem}.activity p{margin:0;color:var(--g3);font-size:.86rem;line-height:1.55}.meta{text-align:right;display:flex;flex-direction:column;gap:.4rem;align-items:flex-end}.time{font-size:.78rem;color:var(--g4)}.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .55rem;border-radius:999px;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.badge.pending{background:rgba(245,158,11,.12);color:#fbbf24;border:1px solid rgba(245,158,11,.2)}.badge.approved{background:rgba(16,185,129,.12);color:#34d399;border:1px solid rgba(16,185,129,.2)}.badge.archived,.badge.rejected{background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.2)}.empty{padding:2rem 1.25rem;text-align:center;color:var(--g4)}.empty strong{display:block;color:var(--w);margin-bottom:.4rem}
    @media (max-width:1050px){.hero{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:720px){header{flex-direction:column;align-items:flex-start}.actions{width:100%;justify-content:space-between}main{padding:1rem .9rem 2rem}.stats{grid-template-columns:1fr}.feed li{grid-template-columns:44px 1fr}.meta{grid-column:2;align-items:flex-start;text-align:left}}
  </style>
</head>
<body>
  <header>
    <a class="brand" href="admin-dashboard.php">
      <div class="brand-mark">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
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
      <div class="card"><span class="eyebrow">Admin Overview</span><h1>Dashboard Page Design</h1><p>Track lost reports, found reports, claims, and returned items in one place. The live activity feed below refreshes automatically so counts stay current without reloading.</p></div>
      <div class="card"><span class="eyebrow">Current Snapshot</span><h1 id="refreshClock" style="margin:0 0 .35rem;font-size:1.85rem;">Loading...</h1><p>Last updated from the database</p></div>
    </section>
    <section class="stats" aria-label="Dashboard summary cards">
      <article class="card stat lost"><span class="label">Lost Items</span><div class="value" id="lostCount"><?php echo (int)$stats['lost']; ?></div><div class="note">Items currently active in the lost inventory.</div><div class="mini">Live from <strong>lost_items</strong></div></article>
      <article class="card stat found"><span class="label">Found Items</span><div class="value" id="foundCount"><?php echo (int)$stats['found']; ?></div><div class="note">Reports entered by users who found property.</div><div class="mini">Live from <strong>found_items</strong></div></article>
      <article class="card stat claimed"><span class="label">Claimed Items</span><div class="value" id="claimedCount"><?php echo (int)$stats['claimed']; ?></div><div class="note">Distinct found items that already received a claim.</div><div class="mini">Pending claims: <strong id="pendingClaimsCard"><?php echo (int)$stats['pending_claims']; ?></strong></div></article>
      <article class="card stat returned"><span class="label">Returned Items</span><div class="value" id="returnedCount"><?php echo (int)$stats['returned']; ?></div><div class="note">Reports marked returned or archived by admins.</div><div class="mini">Moderation complete</div></article>
    </section>
    <section class="panel" aria-labelledby="activityTitle">
      <div class="panel-head"><div><h2 class="panel-title" id="activityTitle">Recent Activity Feed</h2><p class="panel-sub">Latest 10 events from reports and claims.</p></div></div>
      <ul class="feed" id="activityFeed">
        <?php if (!empty($activities)): ?>
          <?php foreach ($activities as $activity): ?>
            <?php $kind = ($activity['entity_type'] === 'claim') ? 'claim' : (($activity['entity_type'] === 'found_items') ? 'found' : 'lost'); ?>
            <li>
              <div class="icon <?php echo htmlspecialchars($kind); ?>">
                <?php if ($kind === 'lost'): ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?php elseif ($kind === 'found'): ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><?php else: ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><?php endif; ?>
              </div>
              <div class="activity"><h3><?php echo htmlspecialchars($activity['action_type']); ?> - <?php echo htmlspecialchars($activity['entity_type']); ?> #<?php echo (int)$activity['entity_id']; ?></h3><p><?php echo htmlspecialchars($activity['action_details'] ?? ($activity['admin_name'] . ' updated the record.')); ?></p></div>
              <div class="meta"><span class="badge <?php echo htmlspecialchars(strtolower($activity['action_type'])); ?>"><?php echo htmlspecialchars($activity['action_type']); ?></span><span class="time"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($activity['created_at']))); ?></span></div>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="empty"><strong>No activity yet.</strong> Recent report changes and claim moderation will appear here.</li>
        <?php endif; ?>
      </ul>
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

    function esc(value) { return String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;'); }
    function fmt(value) { return new Date(value).toLocaleString([], { month:'short', day:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }); }
    function icon(kind) {
      if (kind === 'found') return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
      if (kind === 'claim') return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
      return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    }
    function renderActivities(items) {
      if (!items.length) { activityFeed.innerHTML = '<li class="empty"><strong>No activity yet.</strong> Recent report changes and claim moderation will appear here.</li>'; return; }
      activityFeed.innerHTML = items.map((item) => {
        const kind = item.entity_type === 'claim' ? 'claim' : (item.entity_type === 'found_items' ? 'found' : 'lost');
        return `<li><div class="icon ${esc(kind)}">${icon(kind)}</div><div class="activity"><h3>${esc(item.action_type)} - ${esc(item.entity_type)} #${esc(item.entity_id)}</h3><p>${esc(item.action_details || (item.admin_name + ' updated the record.'))}</p></div><div class="meta"><span class="badge ${esc(String(item.action_type).toLowerCase())}">${esc(item.action_type)}</span><span class="time">${fmt(item.created_at)}</span></div></li>`;
      }).join('');
    }
    async function refreshDashboard() {
      try {
        const response = await fetch('admin-dashboard.php?format=json', { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        lostCount.textContent = data.stats.lost ?? 0;
        foundCount.textContent = data.stats.found ?? 0;
        claimedCount.textContent = data.stats.claimed ?? 0;
        returnedCount.textContent = data.stats.returned ?? 0;
        pendingClaimsHeader.textContent = data.stats.pending_claims ?? 0;
        pendingClaimsCard.textContent = data.stats.pending_claims ?? 0;
        refreshClock.textContent = fmt(data.refreshed_at);
        renderActivities(data.activities || []);
      } catch (e) { console.error(e); }
    }
    refreshClock.textContent = fmt(new Date().toISOString());
    setInterval(refreshDashboard, 60000);
  </script>
</body>
</html>
