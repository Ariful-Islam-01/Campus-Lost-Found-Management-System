<?php
// admin-reports.php
session_start();

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

if (empty($user['role']) || strtolower($user['role']) !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$allowedCategories = ['Electronics', 'Books & Stationery', 'Keys & Cards', 'Clothing & Accessories', 'Others'];
$allowedModerationStatuses = ['Approved', 'Archived'];
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $reportType = isset($_POST['report_type']) ? trim($_POST['report_type']) : '';
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

    if (!in_array($reportType, ['lost', 'found'], true) || $reportId <= 0) {
        $message = 'Invalid report selection.';
        $messageType = 'error';
    } elseif ($action === 'approve' || $action === 'archive') {
        $newStatus = $action === 'approve' ? 'Approved' : 'Archived';
        if (setReportModerationStatus($reportType, $reportId, $newStatus)) {
            logAdminAction($user['id'], ucfirst($action), $reportType, $reportId, 'Admin ' . $action . 'd the report.');
            $message = 'Report updated successfully.';
        } else {
            $message = 'Unable to update report status.';
            $messageType = 'error';
        }
    }
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!in_array($type, ['all', 'lost', 'found'], true)) {
    $type = 'all';
}
if ($status !== '' && !in_array($status, array_merge($allowedModerationStatuses, ['Pending']), true)) {
    $status = '';
}

$reports = getAllReports(['type' => $type, 'status' => $status, 'search' => $search]);
$pendingCount = (int)count(array_filter($reports, function ($report) { return ($report['moderation_status'] ?? '') === 'Pending'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Reports | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--c2:#0d9488;--c3:#2dd4bf;--c4:#fbbf24;--c5:#10b981;--c6:#ef4444;--w:#fff;--g2:#e5e7eb;--g3:#d1d5db;--g4:#9ca3af;--g5:#111827;--r1:12px;--r2:20px;--s:0 18px 50px rgba(0,0,0,.16),0 6px 20px rgba(0,0,0,.10)}
    *{box-sizing:border-box} body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(180deg,#101827 0%,#0c1420 100%);color:var(--w)}
    header{position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.3rem;background:rgba(8,15,28,.8);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08)}
    .title{display:flex;align-items:center;gap:.8rem}.title h1{margin:0;font-size:1.2rem}.title p{margin:.15rem 0 0;color:var(--g4);font-size:.8rem}
    .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem .85rem;border-radius:999px;text-decoration:none;font-size:.82rem;font-weight:700;white-space:nowrap}.btn.primary{background:linear-gradient(135deg,var(--c2),var(--c3));color:var(--w)}.btn.ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--g2)}.btn.logout{background:rgba(239,68,68,.16);border:1px solid rgba(239,68,68,.22);color:#fee2e2}
    main{max-width:1300px;margin:0 auto;padding:1.8rem 1.2rem 2.5rem}.hero{display:flex;justify-content:space-between;gap:1rem;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap}.hero .card,.filters,.table-wrap,.notice{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:var(--r2);box-shadow:var(--s);backdrop-filter:blur(12px)}
    .hero .card{padding:1.1rem 1.25rem}.eyebrow{display:inline-flex;padding:.3rem .65rem;border-radius:999px;background:rgba(13,148,136,.12);border:1px solid rgba(13,148,136,.22);color:#5eead4;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.7rem}
    .hero h2{margin:0 0 .35rem;font-size:1.6rem}.hero p{margin:0;color:var(--g4);line-height:1.6}.chips{display:flex;gap:.65rem;flex-wrap:wrap}
    .chip{padding:.4rem .7rem;border-radius:999px;font-size:.8rem;font-weight:700;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--g2)}.chip.warn{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.22);color:var(--c4)}
    .filters{padding:1rem 1.1rem;margin:1rem 0 1.2rem}.filter-form{display:grid;grid-template-columns:1.3fr .9fr .9fr auto;gap:.9rem;align-items:end}.field{display:flex;flex-direction:column;gap:.4rem}.field label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--g4)}.input,select.input{width:100%;padding:.7rem .85rem;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:var(--w);font-family:inherit;outline:none}.input:focus,select.input:focus{border-color:var(--c2);box-shadow:0 0 0 3px rgba(13,148,136,.16)}
    .table-wrap{overflow:hidden}.table-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08)}.table-head h3{margin:0;font-size:1rem}.table-head p{margin:.25rem 0 0;color:var(--g4);font-size:.85rem}
    table{width:100%;border-collapse:collapse}.table-scroll{overflow:auto}.table thead th{text-align:left;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--g4);padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.02)}.table tbody td{padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);vertical-align:top}.table tbody tr:hover{background:rgba(255,255,255,.03)}
    .status,.mod{display:inline-flex;align-items:center;padding:.3rem .55rem;border-radius:999px;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.status{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);color:var(--g2)}.mod.approved{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.22);color:#34d399}.mod.archived{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.22);color:#f87171}.mod.pending{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.22);color:#fbbf24}
    .report-name{font-weight:800;margin:0 0 .3rem}.report-meta{color:var(--g4);font-size:.82rem;line-height:1.5}.actions{display:flex;flex-wrap:wrap;gap:.45rem}.action-btn{padding:.48rem .65rem;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:var(--w);font-size:.78rem;font-weight:700;text-decoration:none;cursor:pointer}.action-btn.approve{border-color:rgba(16,185,129,.24);background:rgba(16,185,129,.1);color:#34d399}.action-btn.archive{border-color:rgba(239,68,68,.24);background:rgba(239,68,68,.1);color:#f87171}.action-btn.edit{border-color:rgba(13,148,136,.24);background:rgba(13,148,136,.1);color:#5eead4}
    .notice{padding:.9rem 1rem;margin:0 0 1rem}.notice.success{border-color:rgba(16,185,129,.22);color:#86efac}.notice.error{border-color:rgba(239,68,68,.22);color:#fca5a5}
    .empty{padding:2rem 1rem;text-align:center;color:var(--g4)}
    @media (max-width: 1000px){.filter-form{grid-template-columns:1fr 1fr}.filter-form .field:last-child{grid-column:1/-1}.actions{justify-content:flex-start}}
    @media (max-width: 680px){header{flex-direction:column;align-items:flex-start}.filter-form{grid-template-columns:1fr}.table thead{display:none}.table tbody tr{display:block;padding:.85rem 0}.table tbody td{display:block;padding:.45rem 1.1rem;border-bottom:0}.table tbody td::before{content:attr(data-label) ": ";color:var(--g4);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.actions{padding-top:.3rem}}
  </style>
</head>
<body>
<header>
  <div class="title">
    <div class="brand-mark" style="width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--c2),var(--c4));"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></div>
    <div><h1>Admin Report Management</h1><p>Approve, archive, and edit lost/found reports with audit logging.</p></div>
  </div>
  <div style="display:flex;gap:.7rem;flex-wrap:wrap;justify-content:flex-end;align-items:center;">
    <a class="btn ghost" href="admin-dashboard.php">Dashboard</a>
    <a class="btn logout" href="admin-reports.php?action=logout">Logout</a>
  </div>
</header>
<main>
  <section class="hero">
    <div class="card">
      <span class="eyebrow">Admin Table</span>
      <h2>All reports in one place</h2>
      <p>Use the table below to review every lost and found report, approve new submissions, archive outdated items, and edit records when necessary. Every action is logged to the audit trail.</p>
    </div>
    <div class="card">
      <span class="eyebrow">Pending moderation</span>
      <h2 style="margin:0;font-size:2.2rem;"><?php echo (int)$pendingCount; ?></h2>
      <p>Reports waiting for admin review</p>
    </div>
  </section>

  <?php if ($message): ?>
    <div class="notice <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <section class="filters">
    <form class="filter-form" method="get" action="admin-reports.php">
      <div class="field">
        <label for="search">Search</label>
        <input class="input" type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item, reporter, or location">
      </div>
      <div class="field">
        <label for="type">Type</label>
        <select class="input" id="type" name="type">
          <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All</option>
          <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost</option>
          <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found</option>
        </select>
      </div>
      <div class="field">
        <label for="status">Moderation</label>
        <select class="input" id="status" name="status">
          <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
          <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="Archived" <?php echo $status === 'Archived' ? 'selected' : ''; ?>>Archived</option>
        </select>
      </div>
      <div class="field"><button class="btn primary" type="submit" style="border:0;cursor:pointer;">Apply Filters</button></div>
    </form>
  </section>

  <section class="table-wrap">
    <div class="table-head">
      <div>
        <h3>Report List</h3>
        <p><?php echo count($reports); ?> reports found</p>
      </div>
      <a class="btn ghost" href="admin-reports.php">Reset</a>
    </div>
    <div class="table-scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Report</th>
            <th>Type</th>
            <th>Reporter</th>
            <th>Moderation</th>
            <th>Item Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($reports)): ?>
            <?php foreach ($reports as $report): ?>
              <tr>
                <td data-label="Report">
                  <div class="report-name"><?php echo htmlspecialchars($report['item_name']); ?></div>
                  <div class="report-meta"><?php echo htmlspecialchars($report['category']); ?> · <?php echo htmlspecialchars($report['location']); ?></div>
                  <div class="report-meta">#<?php echo (int)$report['id']; ?></div>
                </td>
                <td data-label="Type"><span class="status"><?php echo htmlspecialchars(ucfirst($report['report_type'])); ?></span></td>
                <td data-label="Reporter">
                  <div class="report-meta"><?php echo htmlspecialchars($report['reporter_name']); ?></div>
                  <div class="report-meta"><?php echo htmlspecialchars($report['reporter_email']); ?></div>
                </td>
                <td data-label="Moderation"><span class="mod <?php echo htmlspecialchars(strtolower($report['moderation_status'])); ?>"><?php echo htmlspecialchars($report['moderation_status']); ?></span></td>
                <td data-label="Item Status"><span class="status"><?php echo htmlspecialchars($report['status']); ?></span></td>
                <td data-label="Created"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($report['report_created_at']))); ?></td>
                <td data-label="Actions">
                  <div class="actions">
                    <a class="action-btn edit" href="admin-report-edit.php?type=<?php echo htmlspecialchars($report['report_type']); ?>&id=<?php echo (int)$report['id']; ?>">Edit</a>
                    <?php if ($report['moderation_status'] !== 'Approved'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report['report_type']); ?>">
                        <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                        <button class="action-btn approve" type="submit">Approve</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($report['moderation_status'] !== 'Archived'): ?>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Archive this report?');">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report['report_type']); ?>">
                        <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                        <button class="action-btn archive" type="submit">Archive</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7"><div class="empty">No reports match the selected filters.</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
