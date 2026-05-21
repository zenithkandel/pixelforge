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
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $target_id = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'set_balance' && $target_id > 0) {
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount >= 0) {
            $db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$amount, $target_id]);
            log_admin('USER_MGMT', "Admin set balance to $amount for user #$target_id", ['user_id' => $target_id]);
            $messages[] = ['type' => 'success', 'text' => 'Balance updated successfully.'];
        }
    } elseif ($action === 'adjust_balance' && $target_id > 0) {
        $delta = (int) ($_POST['delta'] ?? 0);
        $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$delta, $target_id]);
        log_admin('USER_MGMT', "Admin adjusted balance by $delta for user #$target_id", ['user_id' => $target_id]);
        $messages[] = ['type' => 'success', 'text' => 'Balance adjusted successfully.'];
    } elseif ($action === 'change_role' && $target_id > 0) {
        $new_role = $_POST['role'] === 'admin' ? 'admin' : 'user';

        $stmt = $db->query('SELECT COUNT(*) FROM users WHERE role = "admin"');
        $admin_count = (int) $stmt->fetchColumn();

        if ($new_role === 'user' && $admin_count <= 1 && $target_id === (int) $_SESSION['user_id']) {
            $messages[] = ['type' => 'error', 'text' => 'Cannot demote yourself — you are the last admin.'];
        } else {
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$new_role, $target_id]);
            log_admin('USER_MGMT', "Admin set role to $new_role for user #$target_id", ['user_id' => $target_id]);
            $messages[] = ['type' => 'success', 'text' => "Role updated to $new_role."];
        }
    } elseif ($action === 'delete_user' && $target_id > 0) {
        if ($target_id === (int) $_SESSION['user_id']) {
            $messages[] = ['type' => 'error', 'text' => 'Cannot delete yourself.'];
        } else {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$target_id]);
            log_admin('USER_MGMT', "Admin deleted user #$target_id", ['user_id' => $target_id]);
            $messages[] = ['type' => 'success', 'text' => 'User permanentely removed.'];
        }
    } elseif ($action === 'reset_streak' && $target_id > 0) {
        $db->prepare('UPDATE users SET streak_days = 0 WHERE id = ?')->execute([$target_id]);
        log_admin('USER_MGMT', "Admin reset streak for user #$target_id", ['user_id' => $target_id]);
        $messages[] = ['type' => 'success', 'text' => 'User streak has been reset.'];
    }
}

try {
    $count_sql = 'SELECT COUNT(*) FROM users';
    $users_sql = 'SELECT u.*, COUNT(p.id) AS pixel_count FROM users u LEFT JOIN pixels p ON u.id = p.owner_id';
    $params = [];

    if (!empty($search)) {
        $count_sql .= ' WHERE username LIKE ? OR email LIKE ?';
        $users_sql .= ' WHERE u.username LIKE ? OR u.email LIKE ?';
        $params = ["%$search%", "%$search%"];
    }

    $users_sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset;

    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $stmt = $db->prepare($users_sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $total_pages = ceil($total / $per_page);
} catch (PDOException $e) {
    log_error('DB', 'Admin users query error: ' . $e->getMessage());
    $users = [];
    $total = 0;
}

$page_title = 'User Control';
require_once __DIR__ . '/header.php';
?>

<style>
    .section-card {
        background: #11111a;
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 24px;
        overflow: hidden;
        margin-bottom: 30px;
    }

    .section-header {
        padding: 25px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .section-title {
        font-size: 18px;
        font-weight: 800;
        color: white;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    .pro-table-wrapper {
        overflow-x: auto;
    }

    .status-pill {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pill-user {
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-muted);
    }

    .pill-admin {
        background: rgba(124, 58, 237, 0.15);
        color: var(--purple-bright);
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    .manage-row {
        background: rgba(0, 0, 0, 0.2);
        display: none;
    }

    .manage-row.active {
        display: table-row;
    }

    .manage-container {
        padding: 24px 30px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        border-top: 1px solid rgba(124, 58, 237, 0.1);
    }

    .manage-group label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 12px;
        letter-spacing: 1px;
    }

    .inline-form {
        display: flex;
        gap: 10px;
    }

    .inline-form input,
    .inline-form select {
        background: #09090e;
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px 15px;
        border-radius: 12px;
        color: white;
        font-size: 13px;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.03);
        color: var(--text-secondary);
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-action:hover {
        background: var(--purple);
        color: white;
        transform: translateY(-2px);
    }

    .btn-action.btn-delete:hover {
        background: var(--red);
    }

    .alert {
        padding: 15px 25px;
        border-radius: 15px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 15px;
        font-weight: 600;
        font-size: 14px;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--green);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--red);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
</style>

<?php foreach ($messages as $msg): ?>
    <div class="alert alert-<?= $msg['type'] ?>">
        <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($msg['text']) ?>
    </div>
<?php endforeach; ?>

<div class="section-card">
    <div class="section-header">
        <div style="display:flex; align-items:center; gap:20px;">
            <h2 class="section-title">Verified Population</h2>
            <span
                style="background:rgba(255,255,255,0.03); padding:4px 12px; border-radius:50px; font-size:12px; font-weight:700; color:var(--text-muted); border:1px solid rgba(255,255,255,0.05);">
                <?= number_format($total) ?> TOTAL
            </span>
        </div>
        <form method="GET" style="display:flex; gap:10px;">
            <div style="position:relative;">
                <i class="fas fa-search"
                    style="position:absolute; left:15px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:12px;"></i>
                <input type="text" name="search" placeholder="Filter by name or identity..."
                    value="<?= htmlspecialchars($search) ?>"
                    style="padding-left:40px; width:300px; height:44px; background:#09090e; border:1px solid rgba(255,255,255,0.05); border-radius:12px; color:white;">
            </div>
            <button type="submit" class="btn-primary"
                style="height:44px; padding:0 25px; border-radius:12px;">Filter</button>
            <?php if (!empty($search)): ?>
                <a href="users.php" class="btn-secondary"
                    style="height:44px; width:44px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:12px;"><i
                        class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="pro-table-wrapper">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Identity</th>
                    <th>Permissions</th>
                    <th>Resources</th>
                    <th>Engagement</th>
                    <th>Observed</th>
                    <th style="text-align:right">Intervention</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:60px 0; color:var(--text-muted);">
                            <i class="fas fa-ghost"
                                style="font-size:40px; margin-bottom:15px; display:block; opacity:0.2;"></i>
                            No matching entities found in the grid.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:15px;">
                                    <div
                                        style="width:40px; height:40px; border-radius:12px; background:<?= htmlspecialchars($u['avatar_color']) ?>20; border:1px solid <?= htmlspecialchars($u['avatar_color']) ?>40; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                        <span
                                            style="font-family:var(--font-game); font-weight:900; color:<?= htmlspecialchars($u['avatar_color']) ?>; text-shadow:0 0 10px <?= htmlspecialchars($u['avatar_color']) ?>60;">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div
                                            style="font-weight:800; color:white; font-size:15px; line-height:1; margin-bottom:4px;">
                                            <?= htmlspecialchars($u['username']) ?>
                                        </div>
                                        <div style="font-size:12px; color:var(--text-muted); font-weight:500;">
                                            <?= htmlspecialchars($u['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="status-pill pill-admin"><i class="fas fa-crown"
                                            style="font-size:9px; margin-right:4px;"></i> SYSTEM ADMIN</span>
                                <?php else: ?>
                                    <span class="status-pill pill-user">STANDARD ENTITY</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <i class="fas fa-coins" style="color:var(--gold); font-size:11px;"></i>
                                        <span
                                            style="font-size:13px; font-weight:800; color:var(--gold);"><?= number_format((int) $u['balance']) ?></span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <i class="fas fa-paint-brush" style="color:var(--purple-bright); font-size:11px;"></i>
                                        <span
                                            style="font-size:12px; font-weight:600; color:var(--text-secondary);"><?= (int) $u['pixel_count'] ?>
                                            PIXELS</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="level-badge" style="font-size:10px; padding:2px 8px;">LV
                                        <?= (int) $u['level'] ?>
                                    </div>
                                    <div
                                        style="display:flex; align-items:center; gap:4px; font-size:12px; color:var(--red); font-weight:700;">
                                        <i class="fas fa-fire"></i> <?= (int) $u['streak_days'] ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px; font-weight:700; color:var(--text-muted);">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </div>
                            </td>
                            <td style="text-align:right">
                                <button class="btn-action toggle-manage" data-target="manage-<?= $u['id'] ?>"
                                    title="Administrative Intervention">
                                    <i class="fas fa-sliders-h"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="manage-row" id="manage-<?= $u['id'] ?>">
                            <td colspan="6">
                                <div class="manage-container">
                                    <div class="manage-group">
                                        <label>Currency Override</label>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="action" value="set_balance">
                                            <input type="number" name="amount" value="<?= (int) $u['balance'] ?>" min="0"
                                                style="width:100px;">
                                            <button type="submit" class="btn-primary"
                                                style="padding:0 15px; height:38px; border-radius:10px;"><i
                                                    class="fas fa-check"></i></button>
                                        </form>
                                    </div>
                                    <div class="manage-group">
                                        <label>Rapid Adjustment (Delta)</label>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="action" value="adjust_balance">
                                            <input type="number" name="delta" placeholder="± Amount" style="width:100px;">
                                            <button type="submit" class="btn-secondary"
                                                style="padding:0 15px; height:38px; border-radius:10px;"><i
                                                    class="fas fa-plus-minus"></i></button>
                                        </form>
                                    </div>
                                    <div class="manage-group">
                                        <label>Authorization Level</label>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="action" value="change_role">
                                            <select name="role" style="width:120px;">
                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin
                                                </option>
                                            </select>
                                            <button type="submit" class="btn-secondary"
                                                style="padding:0 15px; height:38px; border-radius:10px;"><i
                                                    class="fas fa-sync"></i></button>
                                        </form>
                                    </div>
                                    <div class="manage-group">
                                        <label>System Commands</label>
                                        <div style="display:flex; gap:10px;">
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="action" value="reset_streak">
                                                <button type="submit" class="btn-secondary"
                                                    style="height:38px; border-radius:10px; font-weight:700; font-size:12px; flex:1;"
                                                    onclick="return confirm('Kill streak for <?= addslashes($u['username']) ?>?')">RESET
                                                    STREAK</button>
                                            </form>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="btn-danger"
                                                    style="height:38px; border-radius:10px; font-weight:700; font-size:12px; flex:1; background:rgba(239, 68, 68, 0.1); color:var(--red); border:1px solid rgba(239, 68, 68, 0.2);"
                                                    onclick="return confirm('DESTRUCT SEQUENCE: Remove <?= addslashes($u['username']) ?> permanentely?')">TERMINATE</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <div style="display:flex; justify-content:center; gap:8px; margin-top:30px;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="btn-action"
                style="width:auto; padding:0 15px;"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                class="btn-action <?= $i === $page ? 'btn-primary' : '' ?>"
                style="width:40px; <?= $i === $page ? 'background:var(--purple); color:white; border-color:var(--purple-bright);' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="btn-action"
                style="width:auto; padding:0 15px;"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toggle-manage').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.dataset.target;
                var targetRow = document.getElementById(targetId);
                var isActive = targetRow.classList.contains('active');

                // Close all first for accordion effect or just toggle
                // document.querySelectorAll('.manage-row').forEach(r => r.classList.remove('active'));

                if (isActive) {
                    targetRow.classList.remove('active');
                    this.style.background = '';
                    this.style.color = '';
                } else {
                    targetRow.classList.add('active');
                    this.style.background = 'var(--purple)';
                    this.style.color = 'white';
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>