<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = get_db();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $target_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'set_balance' && $target_id > 0) {
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount >= 0) {
            $db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$amount, $target_id]);
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'set_balance', 'user', $target_id, "Set balance to $amount"]);
            $messages[] = 'Balance updated.';
        }
    } elseif ($action === 'adjust_balance' && $target_id > 0) {
        $delta = (int)($_POST['delta'] ?? 0);
        $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$delta, $target_id]);
        $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
           ->execute([(int)$_SESSION['user_id'], 'adjust_balance', 'user', $target_id, "Adjusted balance by $delta"]);
        $messages[] = 'Balance adjusted.';
    } elseif ($action === 'change_role' && $target_id > 0) {
        $new_role = $_POST['role'] === 'admin' ? 'admin' : 'user';

        $stmt = $db->query('SELECT COUNT(*) FROM users WHERE role = "admin"');
        $admin_count = (int)$stmt->fetchColumn();

        if ($new_role === 'user' && $admin_count <= 1 && $target_id === (int)$_SESSION['user_id']) {
            $messages[] = 'Cannot demote yourself — you are the only admin.';
        } else {
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$new_role, $target_id]);
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'change_role', 'user', $target_id, "Set role to $new_role"]);
            $messages[] = 'Role updated.';
        }
    } elseif ($action === 'delete_user' && $target_id > 0) {
        if ($target_id === (int)$_SESSION['user_id']) {
            $messages[] = 'Cannot delete yourself.';
        } else {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$target_id]);
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'delete_user', 'user', $target_id, 'User deleted']);
            $messages[] = 'User deleted.';
        }
    } elseif ($action === 'reset_streak' && $target_id > 0) {
        $db->prepare('UPDATE users SET streak_days = 0 WHERE id = ?')->execute([$target_id]);
        $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
           ->execute([(int)$_SESSION['user_id'], 'reset_streak', 'user', $target_id, 'Streak reset']);
        $messages[] = 'Streak reset.';
    }
}

try {
    $count_sql = 'SELECT COUNT(*) FROM users';
    $users_sql = 'SELECT u.*, COUNT(p.id) AS pixel_count FROM users u LEFT JOIN pixels p ON u.id = p.owner_id';
    $params = [];

    if (!empty($search)) {
        $count_sql .= ' WHERE u.username LIKE ? OR u.email LIKE ?';
        $users_sql .= ' WHERE u.username LIKE ? OR u.email LIKE ?';
        $params = ["%$search%", "%$search%"];
    }

    $users_sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset;

    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare($users_sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $total_pages = ceil($total / $per_page);
} catch (PDOException $e) {
    log_error('DB', 'Admin users query error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    $users = [];
    $total = 0;
    $total_pages = 0;
}

$page_title = 'Users';
require_once __DIR__ . '/header.php';
?>

<div class="admin-section">
    <div class="admin-section-header">
        <div>
            <h2 class="admin-section-title">User Management</h2>
            <p style="color:var(--text-muted);margin:var(--space-xs) 0 0;font-size:14px;"><?= number_format($total) ?> total users</p>
        </div>
        <form method="GET" style="display:flex;gap:var(--space-sm);">
            <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>" style="width:240px;">
            <button type="submit" class="btn-secondary">Search</button>
        </form>
    </div>
</div>

<?php foreach ($messages as $msg): ?>
<div style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:var(--green);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:var(--space-md);">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endforeach; ?>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th style="width:80px;">Level</th>
                    <th style="width:120px;">Balance</th>
                    <th style="width:70px;">Pixels</th>
                    <th style="width:80px;">Role</th>
                    <th style="width:60px;">Streak</th>
                    <th style="width:100px;">Joined</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="10" style="text-align:center;color:var(--text-muted);padding:var(--space-xl);">No users found</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="font-family:var(--font-mono);color:var(--text-muted);"><?= (int)$u['id'] ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($u['username']) ?>" style="display:flex;align-items:center;gap:var(--space-sm);text-decoration:none;color:var(--text-primary);">
                                <span class="avatar-circle sm" style="background:<?= htmlspecialchars($u['avatar_color']) ?>">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </span>
                                <span style="font-weight:500;"><?= htmlspecialchars($u['username']) ?></span>
                            </a>
                        </td>
                        <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="level-badge">Lv<?= (int)$u['level'] ?></span></td>
                        <td><span class="currency"><?= number_format((int)$u['balance']) ?></span></td>
                        <td><?= (int)$u['pixel_count'] ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-purple">Admin</span>
                            <?php else: ?>
                                <span style="font-size:12px;color:var(--text-muted);">User</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);"><?= (int)$u['streak_days'] ?>d</td>
                        <td style="color:var(--text-muted);font-size:12px;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <button class="btn-secondary btn-sm manage-btn" data-uid="<?= (int)$u['id'] ?>" data-uname="<?= htmlspecialchars($u['username']) ?>" data-balance="<?= (int)$u['balance'] ?>" data-role="<?= $u['role'] ?>">
                                Manage
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;gap:var(--space-xs);margin-top:var(--space-lg);">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn-secondary btn-sm" style="<?= $i === $page ? 'border-color:var(--purple-core);color:var(--purple-bright);' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<div id="user-modal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:480px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-lg);">
            <h3 style="margin:0;">Manage <span id="modal-username"></span></h3>
            <button class="btn-icon modal-close-btn" style="font-size:20px;">✕</button>
        </div>

        <form method="POST" id="modal-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" id="modal-user-id">
            <input type="hidden" name="action" id="modal-action">

            <div class="card" style="margin-bottom:var(--space-md);padding:var(--space-md);">
                <div class="form-group" style="margin-bottom:var(--space-md);">
                    <label style="font-weight:600;margin-bottom:var(--space-sm);display:block;">Set Balance (absolute)</label>
                    <div style="display:flex;gap:var(--space-sm);">
                        <input type="number" name="amount" id="modal-balance" min="0" value="0" style="flex:1;">
                        <button type="button" class="btn-primary modal-action-btn" data-action="set_balance">Set</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:var(--space-md);">
                    <label style="font-weight:600;margin-bottom:var(--space-sm);display:block;">Adjust Balance (delta)</label>
                    <div style="display:flex;gap:var(--space-sm);">
                        <input type="number" name="delta" id="modal-delta" value="0" style="flex:1;">
                        <button type="button" class="btn-secondary modal-action-btn" data-action="adjust_balance">Adjust</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:600;margin-bottom:var(--space-sm);display:block;">Change Role</label>
                    <div style="display:flex;gap:var(--space-sm);align-items:center;">
                        <select name="role" id="modal-role" style="flex:1;">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="button" class="btn-secondary modal-action-btn" data-action="change_role">Change</button>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:var(--space-sm);">
                <button type="button" class="btn-secondary modal-action-btn" data-action="reset_streak" style="flex:1;">
                    Reset Streak
                </button>
                <button type="button" class="btn-danger modal-delete-btn" style="flex:1;">
                    Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('user-modal');

    document.querySelectorAll('.manage-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('modal-user-id').value = this.dataset.uid;
            document.getElementById('modal-username').textContent = this.dataset.uname;
            document.getElementById('modal-balance').value = this.dataset.balance;
            document.getElementById('modal-role').value = this.dataset.role;
            modal.style.display = 'flex';
        });
    });

    document.querySelectorAll('.modal-action-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('modal-action').value = this.dataset.action;
            document.getElementById('modal-form').submit();
        });
    });

    var deleteBtn = document.querySelector('.modal-delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                document.getElementById('modal-action').value = 'delete_user';
                document.getElementById('modal-form').submit();
            }
        });
    }

    document.querySelectorAll('.modal-close-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>