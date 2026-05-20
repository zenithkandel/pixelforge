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
$per_page = 25;
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

$page_title = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1>User Management</h1>
            <p><?= number_format($total) ?> users</p>
        </div>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
            <button type="submit" class="btn-secondary btn-sm">Search</button>
        </form>
    </div>

    <?php foreach ($messages as $msg): ?>
        <div style="background:rgba(34,197,94,0.1);color:var(--green);padding:10px 16px;border-radius:8px;margin-bottom:12px;"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Email</th>
                <th>Level</th>
                <th>Balance</th>
                <th>Pixels</th>
                <th>Role</th>
                <th>Streak</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="avatar-circle" style="background:<?= htmlspecialchars($u['avatar_color']) ?>;width:28px;height:28px;font-size:12px;"><?= strtoupper(substr($u['username'], 0, 1)) ?></span>
                        <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($u['username']) ?>" style="color:var(--text-primary);font-weight:500;text-decoration:none;"><?= htmlspecialchars($u['username']) ?></a>
                    </div>
                </td>
                <td style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="level-badge">Lv<?= (int)$u['level'] ?></span></td>
                <td class="currency"><?= number_format((int)$u['balance']) ?> 💰</td>
                <td><?= (int)$u['pixel_count'] ?></td>
                <td><span style="font-size:12px;font-weight:600;color:<?= $u['role'] === 'admin' ? 'var(--purple-bright)' : 'var(--text-secondary)' ?>;"><?= htmlspecialchars($u['role']) ?></span></td>
                <td><?= (int)$u['streak_days'] ?>d</td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <button class="btn-secondary btn-sm" onclick="openUserModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', <?= (int)$u['balance'] ?>, '<?= $u['role'] ?>')">Manage</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:var(--space-lg);">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn-secondary btn-sm" style="<?= $i === $page ? 'border-color:var(--purple-core);color:var(--purple-bright);' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div id="user-modal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Manage <span id="modal-username"></span></h3>
        <form method="POST" id="modal-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" id="modal-user-id">
            <input type="hidden" name="action" id="modal-action">

            <div class="form-group">
                <label>Set Balance (absolute)</label>
                <div style="display:flex;gap:8px;">
                    <input type="number" name="amount" id="modal-balance" min="0" value="0">
                    <button type="button" class="btn-secondary btn-sm" onclick="setAction('set_balance')">Set</button>
                </div>
            </div>

            <div class="form-group">
                <label>Adjust Balance (delta)</label>
                <div style="display:flex;gap:8px;">
                    <input type="number" name="delta" id="modal-delta" value="0">
                    <button type="button" class="btn-secondary btn-sm" onclick="setAction('adjust_balance')">Adjust</button>
                </div>
            </div>

            <div class="form-group">
                <label>Change Role</label>
                <select name="role" id="modal-role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="button" class="btn-secondary btn-sm" onclick="setAction('change_role')" style="margin-top:8px;">Change Role</button>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="button" class="btn-secondary btn-sm" onclick="setAction('reset_streak')">Reset Streak</button>
                <button type="button" class="btn-danger btn-sm" onclick="if(confirm('Delete this user?')){setAction('delete_user');}">Delete</button>
            </div>
        </form>
        <div style="text-align:right;margin-top:16px;">
            <button class="btn-secondary btn-sm" onclick="document.getElementById('user-modal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
function openUserModal(id, username, balance, role) {
    document.getElementById('modal-user-id').value = id;
    document.getElementById('modal-username').textContent = username;
    document.getElementById('modal-balance').value = balance;
    document.getElementById('modal-role').value = role;
    document.getElementById('user-modal').style.display = 'flex';
}

function setAction(action) {
    document.getElementById('modal-action').value = action;
    document.getElementById('modal-form').submit();
}
</script>
<script src="<?= BASE_URL ?>/assets/js/admin-users.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
