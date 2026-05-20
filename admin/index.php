<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp.php';
require_admin();

$db = get_db();

$stats = [];
try {
    $stats['users'] = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['pixels'] = (int)$db->query('SELECT COUNT(*) FROM pixels')->fetchColumn();
    $stats['currency'] = (int)$db->query('SELECT SUM(balance) FROM users')->fetchColumn() ?? 0;
    $stats['active'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE last_login_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
    $stats['fill_pct'] = $stats['pixels'] > 0 ? round($stats['pixels'] / 100, 2) : 0;

    $stmt = $db->query('SELECT a.*, u.username FROM admin_log a JOIN users u ON a.admin_id = u.id ORDER BY a.performed_at DESC LIMIT 20');
    $admin_logs = $stmt->fetchAll();

    $recent_users = $db->query('SELECT username, avatar_color, level, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();
    $total_xp = (int)$db->query('SELECT SUM(xp) FROM users')->fetchColumn() ?? 0;
} catch (PDOException $e) {
    log_error('DB', 'Admin dashboard error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    $admin_logs = [];
    $recent_users = [];
}

$page_title = 'Dashboard';
require_once __DIR__ . '/header.php';
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h2 class="admin-section-title">Overview</h2>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:var(--space-md);">
        <div class="stat-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-sm);">
                <span style="font-size:28px;">👥</span>
                <span class="badge badge-purple">Users</span>
            </div>
            <div class="stat-value"><?= number_format($stats['users']) ?></div>
            <div class="stat-label">Total registered</div>
        </div>

        <div class="stat-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-sm);">
                <span style="font-size:28px;">🎨</span>
                <span class="badge badge-green">Pixels</span>
            </div>
            <div class="stat-value"><?= number_format($stats['pixels']) ?></div>
            <div class="stat-label"><?= $stats['fill_pct'] ?>% of canvas</div>
        </div>

        <div class="stat-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-sm);">
                <span style="font-size:28px;">💰</span>
                <span class="badge badge-gold">Currency</span>
            </div>
            <div class="stat-value currency"><?= number_format($stats['currency']) ?></div>
            <div class="stat-label">In circulation</div>
        </div>

        <div class="stat-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-sm);">
                <span style="font-size:28px;">⚡</span>
                <span class="badge badge-green">Active</span>
            </div>
            <div class="stat-value"><?= number_format($stats['active']) ?></div>
            <div class="stat-label">Active today</div>
        </div>

        <div class="stat-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-sm);">
                <span style="font-size:28px;">⭐</span>
                <span class="badge badge-purple">XP</span>
            </div>
            <div class="stat-value"><?= number_format($total_xp) ?></div>
            <div class="stat-label">Total XP earned</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-lg);">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Admin Actions</h3>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn-secondary btn-sm">View All</a>
        </div>
        <?php if (empty($admin_logs)): ?>
            <div class="empty-state" style="padding:var(--space-lg);">
                <div class="empty-icon">📋</div>
                <h3>No actions recorded</h3>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_logs as $log): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:var(--space-sm);">
                                <span class="avatar-circle sm"><?= strtoupper(substr($log['username'], 0, 1)) ?></span>
                                <span><?= htmlspecialchars($log['username']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span style="font-size:12px;padding:2px 8px;background:var(--bg-elevated);border-radius:var(--radius-sm);">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td style="font-size:13px;color:var(--text-muted);">
                            <?= htmlspecialchars($log['target_type'] ?? '—') ?>
                            <?= $log['target_id'] ? '#' . (int)$log['target_id'] : '' ?>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);">
                            <?= date('M j, H:i', strtotime($log['performed_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">New Users</h3>
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn-secondary btn-sm">Manage</a>
        </div>
        <?php if (empty($recent_users)): ?>
            <div class="empty-state" style="padding:var(--space-lg);">
                <div class="empty-icon">👥</div>
                <h3>No users yet</h3>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
                <?php foreach ($recent_users as $user): ?>
                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($user['username']) ?>" style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-sm);border-radius:var(--radius-md);text-decoration:none;color:var(--text-primary);transition:background var(--transition-fast);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='transparent'">
                    <span class="avatar-circle" style="background:<?= htmlspecialchars($user['avatar_color']) ?>;width:36px;height:36px;font-size:14px;">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($user['username']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            Level <?= (int)$user['level'] ?> · Joined <?= date('M j', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-section" style="margin-top:var(--space-lg);">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-md);">
        <a href="<?= BASE_URL ?>/admin/users.php" class="card" style="text-decoration:none;padding:var(--space-lg);text-align:center;transition:all var(--transition-fast);" onmouseover="this.transform='translateY(-2px)'" onmouseout="this.transform='translateY(0)'">
            <div style="font-size:32px;margin-bottom:var(--space-sm);">👥</div>
            <div style="font-weight:600;color:var(--text-primary);">User Management</div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Manage users, roles, balances</div>
        </a>

        <a href="<?= BASE_URL ?>/admin/canvas.php" class="card" style="text-decoration:none;padding:var(--space-lg);text-align:center;transition:all var(--transition-fast);" onmouseover="this.transform='translateY(-2px)'" onmouseout="this.transform='translateY(0)'">
            <div style="font-size:32px;margin-bottom:var(--space-sm);">🎨</div>
            <div style="font-weight:600;color:var(--text-primary);">Canvas Control</div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Erase pixels, reset canvas</div>
        </a>

        <a href="<?= BASE_URL ?>/admin/logs.php" class="card" style="text-decoration:none;padding:var(--space-lg);text-align:center;transition:all var(--transition-fast);" onmouseover="this.transform='translateY(-2px)'" onmouseout="this.transform='translateY(0)'">
            <div style="font-size:32px;margin-bottom:var(--space-sm);">📋</div>
            <div style="font-weight:600;color:var(--text-primary);">Activity Logs</div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">View admin actions history</div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>