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

require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentAdmin = getUserById($_SESSION['user_id']);
if (!$currentAdmin) {
    header('Location: login.php?action=logout');
    exit;
}

if (empty($currentAdmin['role']) || strtolower($currentAdmin['role']) !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// CSRF token to protect action forms.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$filters = [
    'search' => $search,
    'role' => in_array($roleFilter, ['admin', 'user'], true) ? $roleFilter : '',
    'status' => in_array($statusFilter, ['Active', 'Inactive'], true) ? $statusFilter : '',
    'limit' => $perPage,
    'offset' => $offset,
];

$users = getAdminUsers($filters);
$totalUsers = countAdminUsers($filters);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$activeCount = countAdminUsers(['status' => 'Active']);
$inactiveCount = countAdminUsers(['status' => 'Inactive']);

function buildQuery(array $params)
{
    return htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
}

$pageQuery = [
    'search' => $search,
    'role' => $roleFilter,
    'status' => $statusFilter,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin User Management | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4r3x4m4h+2wO1G75kmOe9w+8Xwwk6oFVi+6Fv7eJmYB4jzBXGww9j5gIMB8DQG7B" crossorigin="anonymous">
  <style>
    :root {
      --bg: #071017;
      --surface: rgba(255,255,255,.06);
      --surface-strong: rgba(255,255,255,.1);
      --border: rgba(255,255,255,.12);
      --text: #f8fafc;
      --muted: #94a3b8;
      --radius: 18px;
      --shadow: 0 30px 90px rgba(0,0,0,.25);
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; background: radial-gradient(circle at top left, rgba(13,148,136,.16), transparent 24%), linear-gradient(180deg, #0b1220 0%, #06090f 100%); color: var(--text); }
    a { color: inherit; }
    header { position: sticky; top: 0; z-index: 20; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.2rem 1.5rem; background: rgba(8,15,28,.92); border-bottom: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(14px); }
    .title-group { display: flex; align-items: center; gap: 0.9rem; }
    .title-group h1 { margin: 0; font-size: 1.35rem; }
    .title-group p { margin: 0; color: var(--muted); font-size: 0.95rem; }
    .header-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
    .hero { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 1.4rem 0; }
    .card-panel { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: var(--radius); padding: 1.3rem 1.35rem; box-shadow: var(--shadow); }
    .card-panel h2 { margin: 0 0 .55rem; font-size: 1.2rem; }
    .card-panel p { margin: 0; color: var(--muted); line-height: 1.7; }
    .filter-row { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .table-card { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: var(--radius); box-shadow: var(--shadow); }
    .table-card .table-responsive { margin: 0; }
    .table thead th { color: #cbd5e1; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.12); }
    .table tbody tr:hover { background: rgba(255,255,255,.04); }
    .badge-active { background-color: #10b981; }
    .badge-inactive { background-color: #ef4444; }
    .btn-sm { min-width: 88px; }
    .page-link { color: #cbd5e1; }
    .page-link:hover { color: #fff; }
    .modal-content { background: rgba(6, 10, 15, 0.96); border: 1px solid rgba(255,255,255,.08); }
    .modal-header, .modal-footer { border-color: rgba(255,255,255,.08); }
    .form-control, .form-select { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: var(--text); }
    .form-control:focus, .form-select:focus { background: rgba(255,255,255,.08); color: var(--text); border-color: #0d9488; box-shadow: 0 0 0 0.2rem rgba(13,148,136,.25); }
    @media (max-width: 1040px) {
      .hero { grid-template-columns: 1fr; }
      .filter-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<header>
  <div class="title-group">
    <div class="brand-mark" style="width:44px;height:44px;border-radius:16px;background:linear-gradient(135deg,#0d9488,#f59e0b);display:grid;place-items:center;color:#fff;font-weight:800;">U</div>
    <div>
      <h1>Admin User Management</h1>
      <p>Search, filter, activate, deactivate, and remove campus accounts safely.</p>
    </div>
  </div>
  <div class="header-actions">
    <a class="btn btn-outline-light btn-sm" href="admin-dashboard.php">Dashboard</a>
    <a class="btn btn-outline-light btn-sm" href="admin-reports.php">Reports</a>
    <a class="btn btn-danger btn-sm" href="admin-users.php?action=logout">Logout</a>
  </div>
</header>
<main class="container-fluid px-4 py-3">
  <div class="hero">
    <section class="card-panel">
      <h2>Total users</h2>
      <p class="fs-2 fw-bold"><?php echo (int)$totalUsers; ?></p>
    </section>
    <section class="card-panel">
      <h2>Active</h2>
      <p class="fs-2 fw-bold"><?php echo (int)$activeCount; ?></p>
    </section>
    <section class="card-panel">
      <h2>Deactivated</h2>
      <p class="fs-2 fw-bold"><?php echo (int)$inactiveCount; ?></p>
    </section>
  </div>

  <section class="card-panel mb-4">
    <form class="filter-row" method="get" action="admin-users.php">
      <div>
        <label class="form-label text-uppercase text-muted small">Search</label>
        <input type="text" class="form-control form-control-sm" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div>
        <label class="form-label text-uppercase text-muted small">Role</label>
        <select class="form-select form-select-sm" name="role">
          <option value="">All roles</option>
          <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
          <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
        </select>
      </div>
      <div>
        <label class="form-label text-uppercase text-muted small">Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">All statuses</option>
          <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
          <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Deactivated</option>
        </select>
      </div>
      <div class="d-grid">
        <label class="form-label text-uppercase text-muted small d-block">&nbsp;</label>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success btn-sm w-100">Apply</button>
          <a href="admin-users.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
      </div>
    </form>
  </section>

  <section class="table-card">
    <div class="table-responsive">
      <table class="table table-borderless align-middle mb-0">
        <thead>
          <tr>
            <th>User ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Registration Date</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">No users found matching your filters.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $user): ?>
              <tr data-user="<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>">
                <td>#<?php echo (int)$user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at']))); ?></td>
                <td>
                  <?php if (strtolower($user['status']) === 'active'): ?>
                    <span class="badge badge-active text-white">Active</span>
                  <?php else: ?>
                    <span class="badge badge-inactive text-white">Deactivated</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                    <button type="button" class="btn btn-outline-primary" data-action="view">View</button>
                    <button type="button" class="btn btn-outline-secondary" data-action="edit">Edit</button>
                    <?php if (strtolower($user['status']) === 'active'): ?>
                      <button type="button" class="btn btn-outline-warning" data-action="deactivate">Deactivate</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-outline-success" data-action="activate">Activate</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-danger" data-action="delete" <?php echo $user['id'] === $currentAdmin['id'] ? 'disabled' : ''; ?>>Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top border-white border-opacity-10">
      <div class="text-muted">Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users</div>
      <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="admin-users.php?<?php echo buildQuery(array_merge($pageQuery, ['page' => $page - 1])); ?>">Previous</a>
          </li>
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="admin-users.php?<?php echo buildQuery(array_merge($pageQuery, ['page' => $p])); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="admin-users.php?<?php echo buildQuery(array_merge($pageQuery, ['page' => $page + 1])); ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  </section>
</main>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewUserModalLabel">User details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row gy-3">
          <div class="col-md-6"><strong>ID</strong><p id="viewUserId" class="mb-0"></p></div>
          <div class="col-md-6"><strong>Registered</strong><p id="viewUserCreated" class="mb-0"></p></div>
          <div class="col-md-6"><strong>Full name</strong><p id="viewUserName" class="mb-0"></p></div>
          <div class="col-md-6"><strong>Email</strong><p id="viewUserEmail" class="mb-0"></p></div>
          <div class="col-md-6"><strong>Role</strong><p id="viewUserRole" class="mb-0"></p></div>
          <div class="col-md-6"><strong>Status</strong><p id="viewUserStatus" class="mb-0"></p></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Edit user</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" name="user_id" id="editUserId">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="row gy-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input type="text" class="form-control" name="name" id="editUserName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="editUserEmail" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Role</label>
              <select class="form-select" name="role" id="editUserRole">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <div id="editUserError" class="alert alert-danger d-none mt-4" role="alert"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveEditButton">Save changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="actionModalLabel">Confirm action</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="actionModalMessage"></p>
        <div class="alert alert-warning d-none" id="actionModalWarning"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmActionButton">Confirm</button>
      </div>
    </div>
  </div>
</div>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="pageToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastBody"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-U1h5x0G3Sj2+hN6tDf1DE4BFeN4eQcA/pv3eCG7Q6u0xH7p81Md7UkrhQZHzueU6" crossorigin="anonymous"></script>
<script>
  const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
  const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
  const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
  const toastElement = document.getElementById('pageToast');
  const toastBody = document.getElementById('toastBody');
  const toastInstance = new bootstrap.Toast(toastElement, { delay: 3500 });

  function showToast(message) {
    toastBody.textContent = message;
    toastInstance.show();
  }

  function getUserFromButton(button) {
    const row = button.closest('tr');
    if (!row) return null;
    try {
      return JSON.parse(row.dataset.user);
    } catch (err) {
      return null;
    }
  }

  function openViewModal(user) {
    document.getElementById('viewUserId').textContent = '#' + user.id;
    document.getElementById('viewUserCreated').textContent = new Date(user.created_at).toLocaleDateString();
    document.getElementById('viewUserName').textContent = user.name;
    document.getElementById('viewUserEmail').textContent = user.email;
    document.getElementById('viewUserRole').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
    document.getElementById('viewUserStatus').textContent = user.status;
    viewModal.show();
  }

  function openEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserRole').value = user.role;
    document.getElementById('editUserError').classList.add('d-none');
    editModal.show();
  }

  function openConfirmModal(action, user) {
    const actionLabel = action === 'delete' ? 'Delete account' : action === 'activate' ? 'Activate account' : 'Deactivate account';
    const message = action === 'delete'
      ? `This action cannot be undone. Delete ${user.name} permanently?`
      : action === 'activate'
        ? `Are you sure you want to activate ${user.name}'s account?`
        : `Are you sure you want to deactivate ${user.name}'s account?`;
    document.getElementById('actionModalLabel').textContent = actionLabel;
    document.getElementById('actionModalMessage').textContent = message;
    document.getElementById('actionModalWarning').classList.toggle('d-none', action !== 'delete');
    document.getElementById('actionModalWarning').textContent = action === 'delete' ? 'This user will be permanently deleted from the system.' : '';
    const confirmButton = document.getElementById('confirmActionButton');
    confirmButton.dataset.userId = user.id;
    confirmButton.dataset.action = action;
    confirmButton.textContent = action === 'delete' ? 'Delete' : action === 'activate' ? 'Activate' : 'Deactivate';
    confirmButton.className = action === 'delete' ? 'btn btn-danger' : action === 'activate' ? 'btn btn-success' : 'btn btn-warning';
    actionModal.show();
  }

  function postUserAction(payload) {
    const formData = new URLSearchParams(payload);
    return fetch('user-actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    }).then(async response => {
      const data = await response.json();
      if (!response.ok) {
        throw data;
      }
      return data;
    });
  }

  document.querySelectorAll('button[data-action]').forEach(button => {
    button.addEventListener('click', () => {
      const action = button.dataset.action;
      const user = getUserFromButton(button);
      if (!user) return;

      if (action === 'view') {
        openViewModal(user);
        return;
      }
      if (action === 'edit') {
        openEditModal(user);
        return;
      }
      openConfirmModal(action, user);
    });
  });

  document.getElementById('saveEditButton').addEventListener('click', () => {
    const form = document.getElementById('editUserForm');
    const userId = form.user_id.value;
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const role = form.role.value;
    const csrfToken = form.csrf_token.value;
    const errorBox = document.getElementById('editUserError');
    errorBox.classList.add('d-none');

    if (!name || !email) {
      errorBox.textContent = 'Name and email are required.';
      errorBox.classList.remove('d-none');
      return;
    }

    postUserAction({ action: 'edit', user_id: userId, name, email, role, csrf_token: csrfToken })
      .then(data => {
        editModal.hide();
        showToast(data.message || 'User updated successfully.');
        setTimeout(() => location.reload(), 900);
      })
      .catch(err => {
        errorBox.textContent = err.message || 'Unable to update user.';
        errorBox.classList.remove('d-none');
      });
  });

  document.getElementById('confirmActionButton').addEventListener('click', () => {
    const button = document.getElementById('confirmActionButton');
    const action = button.dataset.action;
    const userId = button.dataset.userId;
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>';

    postUserAction({ action, user_id: userId, csrf_token: csrfToken })
      .then(data => {
        actionModal.hide();
        showToast(data.message || 'Action completed successfully.');
        setTimeout(() => location.reload(), 900);
      })
      .catch(err => {
        actionModal.hide();
        showToast(err.message || 'Unable to complete action.');
      });
  });
</script>
</body>
</html>
