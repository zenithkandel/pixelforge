<aside class="sidebar">
    <div class="brand">PixelForge</div>
    <nav>
        <a href="canvas.php">The Forge</a>
        <a href="game.php">Pixel Dash</a>
        <a href="leaderboard.php">Leaderboard</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="profile.php?username=<?= h($_SESSION['username']) ?>">Profile</a>
            <a href="#" id="logout-btn">Logout</a>
        <?php else: ?>
            <a href="index.php">Login / Register</a>
        <?php endif; ?>
    </nav>
    
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="user-info">
        <div id="pxl-balance">PXL: Loading...</div>
    </div>
    <meta name="csrf-token" content="<?= h(generate_csrf_token()) ?>">
    <script type="module">
        import { apiGet, apiPost } from './assets/js/api.js';
        apiGet('api/auth/me.php').then(res => {
            if(res.ok) {
                document.getElementById('pxl-balance').innerText = 'PXL: ' + res.data.pxl_balance;
            }
        });
        document.getElementById('logout-btn').addEventListener('click', async (e) => {
            e.preventDefault();
            await apiPost('api/auth/logout.php');
            window.location.href = 'index.php';
        });
    </script>
    <?php endif; ?>
</aside>
