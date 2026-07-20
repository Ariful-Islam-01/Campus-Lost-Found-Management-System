<?php
// admin-report-edit.php
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

$reportType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$allowedCategories = ['Electronics', 'Books & Stationery', 'Keys & Cards', 'Clothing & Accessories', 'Others'];
$allowedModerationStatuses = ['Approved', 'Archived'];

if (!in_array($reportType, ['lost', 'found'], true) || $reportId <= 0) {
    header('Location: admin-reports.php');
    exit;
}

$report = getReportByTypeAndId($reportType, $reportId);
if (!$report) {
    header('Location: admin-reports.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $moderationStatus = isset($_POST['moderation_status']) ? trim($_POST['moderation_status']) : 'Approved';

    if ($itemName === '' || strlen($itemName) < 2) {
        $error = 'Item name is required.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Please select a valid category.';
    } elseif ($description === '' || strlen($description) < 10) {
        $error = 'Description must be at least 10 characters.';
    } elseif ($location === '' || strlen($location) < 3) {
        $error = 'Location is required.';
    } elseif (!in_array($moderationStatus, $allowedModerationStatuses, true)) {
        $error = 'Please choose a valid moderation status.';
    } else {
        $updated = updateReportByAdmin($reportType, $reportId, [
            'item_name' => $itemName,
            'category' => $category,
            'description' => $description,
            'location' => $location,
            'moderation_status' => $moderationStatus,
        ]);

        if ($updated) {
            logAdminAction($user['id'], 'Update', $reportType, $reportId, 'Admin updated the report details.');
            header('Location: admin-reports.php?updated=1');
            exit;
        }

        $error = 'Unable to update the report.';
    }
}

$locationLabel = $reportType === 'lost' ? 'Last Seen Location' : 'Pickup Location';
$locationValue = $reportType === 'lost' ? $report['last_seen_location'] : $report['pickup_location'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Report | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--c2:#0d9488;--c3:#2dd4bf;--c4:#fbbf24;--w:#fff;--g2:#e5e7eb;--g3:#d1d5db;--g4:#9ca3af;--g5:#111827;--r1:14px;--r2:20px;--s:0 18px 50px rgba(0,0,0,.16),0 6px 20px rgba(0,0,0,.10)}
    *{box-sizing:border-box} body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(180deg,#101827 0%,#0c1420 100%);color:var(--w)}
    header{position:sticky;top:0;background:rgba(8,15,28,.8);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:1rem 1.2rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem .85rem;border-radius:999px;text-decoration:none;font-size:.82rem;font-weight:700;white-space:nowrap}.btn.primary{background:linear-gradient(135deg,var(--c2),var(--c3));color:var(--w)}.btn.ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--g2)}
    main{max-width:1100px;margin:0 auto;padding:1.8rem 1.2rem 2.5rem}.wrap{display:grid;grid-template-columns:1fr 1.1fr;gap:1rem}.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:var(--r2);box-shadow:var(--s);backdrop-filter:blur(12px)}
    .preview,.form{padding:1.2rem 1.25rem}.eyebrow{display:inline-flex;padding:.3rem .65rem;border-radius:999px;background:rgba(13,148,136,.12);border:1px solid rgba(13,148,136,.22);color:#5eead4;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.7rem}
    h1,h2{margin:0 0 .35rem}.muted{margin:0;color:var(--g4);line-height:1.6}.error{padding:.85rem 1rem;border:1px solid rgba(239,68,68,.22);background:rgba(239,68,68,.12);color:#fca5a5;border-radius:12px;margin-bottom:1rem}
    .field{margin-bottom:1rem;display:flex;flex-direction:column;gap:.4rem}.field label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--g4)}.input,textarea,select{width:100%;padding:.78rem .85rem;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:var(--w);font-family:inherit;outline:none}textarea{min-height:190px;resize:vertical}.input:focus,textarea:focus,select:focus{border-color:var(--c2);box-shadow:0 0 0 3px rgba(13,148,136,.16)}
    .photo{width:100%;max-height:320px;object-fit:cover;border-radius:16px;border:1px solid rgba(255,255,255,.08);margin:.75rem 0 1rem;background:rgba(255,255,255,.04)}
    .meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.meta{padding:.75rem .8rem;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06)}.meta span{display:block;color:var(--g4);font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin-bottom:.35rem}.meta strong{font-size:.92rem}
    .actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem}.actions .btn{border:0;cursor:pointer}
    @media (max-width:900px){.wrap{grid-template-columns:1fr}.meta-grid{grid-template-columns:1fr 1fr}}
    @media (max-width:640px){.meta-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header>
  <div>
    <h1 style="font-size:1.1rem;margin:0 0 .2rem;">Edit Report</h1>
    <div style="color:var(--g4);font-size:.8rem;">Admin-only report modification</div>
  </div>
  <div style="display:flex;gap:.7rem;flex-wrap:wrap;">
    <a class="btn ghost" href="admin-reports.php">Back to reports</a>
    <a class="btn primary" href="admin-dashboard.php">Dashboard</a>
  </div>
</header>
<main>
  <div class="wrap">
    <section class="card preview">
      <span class="eyebrow"><?php echo htmlspecialchars(ucfirst($reportType)); ?> report</span>
      <h2><?php echo htmlspecialchars($report['item_name']); ?></h2>
      <p class="muted"><?php echo htmlspecialchars($report['category']); ?> · Report #<?php echo (int)$report['id']; ?></p>
      <?php if (!empty($report['photo_path'])): ?>
        <img class="photo" src="<?php echo htmlspecialchars($report['photo_path']); ?>" alt="<?php echo htmlspecialchars($report['item_name']); ?>">
      <?php endif; ?>
      <div class="meta-grid">
        <div class="meta"><span>Reporter</span><strong><?php echo htmlspecialchars($report['reporter_name']); ?></strong></div>
        <div class="meta"><span>Reporter Email</span><strong><?php echo htmlspecialchars($report['reporter_email']); ?></strong></div>
        <div class="meta"><span><?php echo htmlspecialchars($locationLabel); ?></span><strong><?php echo htmlspecialchars($locationValue); ?></strong></div>
        <div class="meta"><span>Moderation</span><strong><?php echo htmlspecialchars($report['moderation_status']); ?></strong></div>
      </div>
    </section>

    <section class="card form">
      <span class="eyebrow">Update details</span>
      <h2>Adjust the report record</h2>
      <p class="muted">Edit the report content and keep the moderation state aligned with the admin review decision.</p>
      <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <div class="field">
          <label for="item_name">Item Name</label>
          <input class="input" id="item_name" name="item_name" value="<?php echo htmlspecialchars($report['item_name']); ?>" required>
        </div>
        <div class="field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <?php foreach ($allowedCategories as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $report['category'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" required><?php echo htmlspecialchars($report['description']); ?></textarea>
        </div>
        <div class="field">
          <label for="location"><?php echo htmlspecialchars($locationLabel); ?></label>
          <input class="input" id="location" name="location" value="<?php echo htmlspecialchars($locationValue); ?>" required>
        </div>
        <div class="field">
          <label for="moderation_status">Moderation Status</label>
          <select id="moderation_status" name="moderation_status">
            <option value="Approved" <?php echo $report['moderation_status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="Archived" <?php echo $report['moderation_status'] === 'Archived' ? 'selected' : ''; ?>>Archived</option>
          </select>
        </div>
        <div class="actions">
          <button class="btn primary" type="submit">Save changes</button>
          <a class="btn ghost" href="admin-reports.php">Cancel</a>
        </div>
      </form>
    </section>
  </div>
</main>
</body>
</html>
