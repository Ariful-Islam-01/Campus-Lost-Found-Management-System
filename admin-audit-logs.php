<?php
// admin-audit-logs.php
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

$logs = getAdminAuditLogs(50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Logs | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--c2:#0d9488;--c3:#2dd4bf;--w:#fff;--g2:#e5e7eb;--g3:#d1d5db;--g4:#9ca3af;--s:0 18px 50px rgba(0,0,0,.16),0 6px 20px rgba(0,0,0,.10)}
    *{box-sizing:border-box} body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(180deg,#101827 0%,#0c1420 100%);color:var(--w)}
    header{position:sticky;top:0;background:rgba(8,15,28,.8);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:1rem 1.2rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem .85rem;border-radius:999px;text-decoration:none;font-size:.82rem;font-weight:700;white-space:nowrap}.btn.primary{background:linear-gradient(135deg,var(--c2),var(--c3));color:var(--w)}.btn.ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--g2)}
    main{max-width:1200px;margin:0 auto;padding:1.8rem 1.2rem 2.5rem}.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:20px;box-shadow:var(--s);backdrop-filter:blur(12px);padding:1rem 1.1rem}.head{display:flex;justify-content:space-between;gap:1rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem}.head h1{margin:0;font-size:1.4rem}.head p{margin:.25rem 0 0;color:var(--g4)}
    table{width:100%;border-collapse:collapse}.scroll{overflow:auto}.table thead th{text-align:left;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--g4);padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08)}.table tbody td{padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);vertical-align:top}.table tbody tr:hover{background:rgba(255,255,255,.03)}
    .muted{color:var(--g4);font-size:.82rem;line-height:1.5}.empty{padding:2rem 1rem;text-align:center;color:var(--g4)}
    @media (max-width:760px){.table thead{display:none}.table tbody tr{display:block;padding:.85rem 0}.table tbody td{display:block;padding:.45rem 1.1rem;border-bottom:0}.table tbody td::before{content:attr(data-label) ": ";color:var(--g4);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}}
  </style>
</head>
<body>
<header>
  <div>
    <div style="font-size:1.1rem;font-weight:800;">Admin Audit Logs</div>
    <div style="color:var(--g4);font-size:.8rem;">Every admin action recorded with timestamp and actor</div>
  </div>
  <div style="display:flex;gap:.7rem;flex-wrap:wrap;">
    <a class="btn ghost" href="admin-reports.php">Reports</a>
    <a class="btn primary" href="admin-dashboard.php">Dashboard</a>
  </div>
</header>
<main>
  <section class="card">
    <div class="head">
      <div>
        <h1>Audit Trail</h1>
        <p>Latest 50 moderation and edit events.</p>
      </div>
    </div>
    <div class="scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Admin</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td data-label="Time"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td>
                <td data-label="Admin">
                  <div><?php echo htmlspecialchars($log['admin_name']); ?></div>
                  <div class="muted"><?php echo htmlspecialchars($log['admin_email']); ?></div>
                </td>
                <td data-label="Action"><?php echo htmlspecialchars($log['action_type']); ?></td>
                <td data-label="Entity"><?php echo htmlspecialchars($log['entity_type'] . ' #' . $log['entity_id']); ?></td>
                <td data-label="Details"><?php echo htmlspecialchars($log['action_details'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5"><div class="empty">No audit logs yet.</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>