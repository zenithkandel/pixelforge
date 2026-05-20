<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$page = (int)($_GET['page'] ?? 1);
$per_page = 25;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE username LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$total = Database::fetch("SELECT COUNT(*) as cnt FROM users $where", $params);
$users = Database::fetchAll("
    SELECT u.*, (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id) as pixel_count
    FROM users u
    $where
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
", $params);

$total_pages = ceil($total['cnt'] / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="admin-page">
        <h1>User Management</h1>

        <div class="admin-nav">
            <a href="<?php echo APP_URL; ?>/admin/index.php" class="btn">← Back to Dashboard</a>
            <a href="<?php echo APP_URL; ?>/admin/canvas.php" class="btn">Canvas Management</a>
            <a href="<?php echo APP_URL; ?>/admin/logs.php" class="btn">Admin Logs</a>
        </div>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <table class="admin-table">
            <thead>
                <tr>
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
            <tr data-user-id="<?php echo $u['id']; ?>">
                <td>
                    <div class="player-cell">
                        <div class="avatar small" style="background: <?php echo htmlspecialchars($u['avatar_color']); ?>">
                            <?php echo strtoupper($u['username'][0]); ?>
                        </div>
                        <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($u['username']); ?>" target="_blank">
                            <?php echo htmlspecialchars($u['username']); ?>
                        </a>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo $u['level']; ?></td>
                <td><?php echo $u['balance']; ?></td>
                <td><a href="#" class="pixel-link" data-user="<?php echo $u['id']; ?>"><?php echo $u['pixel_count']; ?></a></td>
                <td>
                    <span class="role-badge <?php echo $u['role']; ?>"><?php echo $u['role']; ?></span>
                </td>
                <td><?php echo $u['streak_days']; ?></td>
                <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                <td>
                    <button class="btn small edit-balance-btn">Edit Balance</button>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <button class="btn small toggle-role-btn" data-user-id="<?php echo $u['id']; ?>" data-current-role="<?php echo $u['role']; ?>">
                        Make <?php echo $u['role'] === 'admin' ? 'User' : 'Admin'; ?>
                    </button>
                    <button class="btn small danger delete-user-btn" data-user-id="<?php echo $u['id']; ?>">Delete</button>
                    <button class="btn small reset-streak-btn" data-user-id="<?php echo $u['id']; ?>">Reset Streak</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </main>

    <div id="action-modal" class="modal hidden">
        <div class="modal-content">
            <h3 id="modal-title">Action</h3>
            <div id="modal-body"></div>
            <div class="modal-actions">
                <button id="modal-confirm" class="btn primary">Save</button>
                <button id="modal-cancel" class="btn">Cancel</button>
            </div>
        </div>
    </div>

    <input type="hidden" id="csrf-token" value="<?php echo csrf_token(); ?>">
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
    </script>
    <script src="<?php echo APP_URL; ?>/assets/js/admin-users.js"></script>
</body>
</html>