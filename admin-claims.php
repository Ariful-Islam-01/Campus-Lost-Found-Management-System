<?php
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

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimId = isset($_POST['claim_id']) ? (int)$_POST['claim_id'] : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $reason = isset($_POST['decision_reason']) ? trim($_POST['decision_reason']) : '';

    if ($claimId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $message = 'Invalid claim action.';
        $messageType = 'error';
    } elseif ($reason === '') {
        $message = 'A reason is required for this decision.';
        $messageType = 'error';
    } else {
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        $updated = setClaimDecision($claimId, $status, $reason, $user['id']);
        if ($updated) {
            notifyClaimantDecision($claimId, $status, $reason, $user['id']);
            logAdminAction($user['id'], $status === 'Approved' ? 'Approve Claim' : 'Reject Claim', 'claim', $claimId, $reason);
            $message = 'Claim decision saved successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to save the claim decision. The claim may have already been processed.';
            $messageType = 'error';
        }
    }
}

$claims = getPendingClaims();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claim Review | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0b1220;
      --surface: rgba(255,255,255,0.04);
      --surface-strong: rgba(255,255,255,0.08);
      --border: rgba(255,255,255,0.12);
      --text: #f8fafc;
      --muted: #94a3b8;
      --success: #34d399;
      --warning: #fbbf24;
      --danger: #f87171;
      --radius: 20px;
      --shadow: 0 24px 70px rgba(0,0,0,.22);
    }

    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', system-ui, sans-serif; min-height: 100vh; background: radial-gradient(circle at top, rgba(13,148,136,0.15), transparent 32%), linear-gradient(180deg, #090f1a 0%, #090f1a 100%); color: var(--text); }
    a { color: inherit; text-decoration: none; }

    header { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.2rem; background: rgba(8,15,28,.92); border-bottom: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(14px); }
    .title-group { display: flex; align-items: center; gap: 0.75rem; }
    .title-group h1 { margin: 0; font-size: 1.2rem; }
    .title-group p { margin: 0; color: var(--muted); font-size: 0.9rem; }
    .header-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.75rem 1rem; border-radius: 999px; font-weight: 700; white-space: nowrap; border: 1px solid transparent; transition: transform 0.2s ease, background 0.2s ease; }
    .btn:hover { transform: translateY(-1px); }
    .btn.primary { background: linear-gradient(135deg, #0f766e, #14b8a6); color: #fff; }
    .btn.ghost { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.1); color: var(--text); }
    .btn.logout { background: rgba(239,68,68,.16); border-color: rgba(239,68,68,.22); color: #fee2e2; }

    main { max-width: 1240px; margin: 0 auto; padding: 1.6rem 1.2rem 2.5rem; }
    .hero { display: grid; grid-template-columns: 1fr auto; gap: 1rem; margin-bottom: 1.5rem; }
    .hero-card { padding: 1.35rem 1.4rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); }
    .hero-card span { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.7rem; border-radius: 999px; background: rgba(13,148,136,.12); color: #5eead4; font-size: 0.75rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .hero-card h2 { margin: 0.85rem 0 0.65rem; font-size: 1.45rem; }
    .hero-card p { margin: 0; color: var(--muted); line-height: 1.7; }

    .notice { padding: 1rem 1.1rem; border-radius: 16px; margin-bottom: 1.25rem; border: 1px solid transparent; }
    .notice.success { background: rgba(16,185,129,.14); border-color: rgba(16,185,129,.28); color: #d1fae5; }
    .notice.error { background: rgba(248,113,113,.14); border-color: rgba(248,113,113,.28); color: #fee2e2; }

    .claim-grid { display: grid; gap: 1.2rem; }
    .claim-card {
      display: grid;
      grid-template-columns: 1.3fr 1.7fr 1fr;
      gap: 1.05rem;
      padding: 1.45rem;
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: var(--radius);
      box-shadow: 0 26px 70px rgba(0,0,0,.18);
      min-height: 260px;
      align-items: stretch;
    }

    .claim-card-section { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .claim-card-section h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #fff; }
    .claim-card-section .subtext { color: var(--muted); font-size: 0.88rem; line-height: 1.7; }

    .claim-info { display: grid; gap: 0.55rem; }
    .claim-info-item { display: flex; justify-content: space-between; gap: 0.75rem; }
    .claim-info-item span:first-child { color: var(--muted); font-size: 0.84rem; }
    .claim-info-item span:last-child { color: #fff; font-weight: 600; text-align: right; min-width: 120px; }

    .proof-box { flex: 1; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 1.1rem; color: var(--muted); font-size: 0.92rem; line-height: 1.75; white-space: pre-wrap; overflow: auto; }
    .proof-box strong { display: block; color: #fff; margin-bottom: 0.65rem; }

    .action-panel { display: grid; gap: 1rem; min-width: 0; }
    .action-summary { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 1rem; display: grid; gap: 0.75rem; }
    .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.45rem 0.85rem; border-radius: 999px; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
    .status-pill.pending { color: #fbbf24; background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.24); }
    .status-pill.found { color: #34d399; background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.22); }
    .status-pill.claimed { color: #60a5fa; background: rgba(59,130,246,.12); border: 1px solid rgba(59,130,246,.22); }

    .field { display: grid; gap: 0.45rem; }
    .field label { color: var(--muted); font-size: 0.84rem; font-weight: 700; }
    .field textarea { width: 100%; min-height: 118px; border-radius: 16px; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.04); color: var(--text); padding: 0.95rem 1rem; resize: vertical; font-family: inherit; font-size: 0.92rem; }

    .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
    .action-buttons button { border: 0; border-radius: 999px; padding: 0.95rem 1rem; font-weight: 700; cursor: pointer; transition: transform 0.18s ease; }
    .action-buttons button:hover { transform: translateY(-1px); }
    .btn-approve { background: #10b981; color: #fff; }
    .btn-reject { background: #ef4444; color: #fff; }
    .btn-secondary { background: rgba(255,255,255,.08); color: var(--text); }

    .claim-note { color: var(--muted); font-size: 0.82rem; line-height: 1.65; }

    @media (max-width: 1040px) {
      .claim-card { grid-template-columns: 1fr; }
    }
    @media (max-width: 620px) {
      .hero { grid-template-columns: 1fr; }
      .action-buttons { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<header>
  <div class="title-group">
    <div class="brand-mark" style="width:40px;height:40px;border-radius:14px;background:linear-gradient(135deg,#0f766e,#14b8a6);display:grid;place-items:center;color:#fff;">C</div>
    <div>
      <h1>Claim Review Panel</h1>
      <p>Approve/reject pending claims and record a decision reason.</p>
    </div>
  </div>
  <div class="header-actions">
    <a class="btn ghost" href="admin-dashboard.php">Dashboard</a>
    <a class="btn primary" href="admin-reports.php">Manage reports</a>
    <a class="btn logout" href="admin-claims.php?action=logout">Logout</a>
  </div>
</header>
<main>
  <section class="hero">
    <div class="hero-card">
      <span>Pending Claims</span>
      <h2><?php echo count($claims); ?> claim<?php echo count($claims) === 1 ? '' : 's'; ?> awaiting review</h2>
      <p>Review each claimant's ownership proof carefully before applying the decision. Approved claims update the found item status to Claimed.</p>
    </div>
    <div class="hero-card">
      <span>Guidance</span>
      <p>Use a clear reason for every decision. The claimant receives both an in-app notification and an email when the decision is recorded.</p>
    </div>
  </section>

  <?php if ($message): ?>
    <div class="notice <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if (empty($claims)): ?>
    <section class="hero-card" style="border-radius:var(--radius);">
      <h2 style="margin:0;">No pending claims</h2>
      <p style="margin:0.9rem 0 0; color: var(--muted);">There are currently no claim requests waiting for admin review.</p>
    </section>
  <?php else: ?>
    <div class="claim-grid">
      <?php foreach ($claims as $claim): ?>
        <article class="claim-card">
          <div class="claim-card-section">
            <div>
              <h3><?php echo htmlspecialchars($claim['item_name']); ?></h3>
              <p class="subtext">Claim ID: #<?php echo (int)$claim['id']; ?> · Found item #<?php echo (int)$claim['found_item_id']; ?></p>
            </div>
            <div class="claim-info">
              <div class="claim-info-item"><span>Claimant</span><strong><?php echo htmlspecialchars($claim['claimant_name']); ?></strong></div>
              <div class="claim-info-item"><span>Email</span><strong><?php echo htmlspecialchars($claim['claimant_email']); ?></strong></div>
              <div class="claim-info-item"><span>Phone</span><strong><?php echo htmlspecialchars($claim['claimant_phone']); ?></strong></div>
              <div class="claim-info-item"><span>Location</span><strong><?php echo htmlspecialchars($claim['pickup_location']); ?></strong></div>
              <div class="claim-info-item"><span>Submitted</span><strong><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($claim['created_at']))); ?></strong></div>
            </div>
          </div>

          <div class="claim-card-section">
            <div class="proof-box">
              <strong>Proof of ownership</strong>
              <?php echo nl2br(htmlspecialchars($claim['proof_of_ownership'])); ?>
            </div>
          </div>

          <div class="claim-card-section action-panel">
            <div class="action-summary">
              <span class="status-pill pending">Pending</span>
              <div class="claim-info-item"><span>Item status</span><strong><?php echo htmlspecialchars($claim['item_status']); ?></strong></div>
              <p class="claim-note">Use the fields below to approve or reject the claim, then provide a short reason so the claimant can understand the decision.</p>
            </div>

            <form method="post" class="claim-action-form">
              <input type="hidden" name="claim_id" value="<?php echo (int)$claim['id']; ?>">
              <div class="field">
                <label for="decision_reason_<?php echo (int)$claim['id']; ?>">Decision reason</label>
                <textarea id="decision_reason_<?php echo (int)$claim['id']; ?>" name="decision_reason" placeholder="Explain why this claim is approved or rejected." required></textarea>
              </div>
              <div class="action-buttons">
                <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
              </div>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
