</main>

<aside class="app-widgets">
    <?php if (isset($nav_user)): ?>
        <div class="section-card">
            <div class="section-header">
                <h3 class="section-title">Operative Status</h3>
            </div>
            <div class="profile-widget" style="padding:20px;">
                <div class="stat-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="stat-box"
                        style="background:rgba(255,255,255,0.03); padding:10px; border:1px solid var(--border-default); text-align:center;">
                        <span class="label"
                            style="display:block; font-size:10px; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">BEST</span>
                        <span class="value"
                            style="font-family:var(--font-game); font-size:16px; color:white;"><?= number_format($nav_user['best_score'] ?? 0) ?></span>
                    </div>
                    <div class="stat-box"
                        style="background:rgba(255,255,255,0.03); padding:10px; border:1px solid var(--border-default); text-align:center;">
                        <span class="label"
                            style="display:block; font-size:10px; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">LVL</span>
                        <span class="value"
                            style="font-family:var(--font-game); font-size:16px; color:var(--accent-blue);"><?= (int) $nav_user['level'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <h3 class="section-title">Commendations</h3>
            </div>
            <div id="achievements-mini-list" style="display:flex; flex-direction:column; gap:10px; padding:20px;">
                <!-- To be populated by JS -->
                <div
                    style="font-size:11px; color:var(--text-muted); text-align:center; text-transform:uppercase; letter-spacing:1px; font-weight:800;">
                    SYNCING_FILES...</div>
            </div>
        </div>
    <?php endif; ?>
</aside>

<footer class="app-footer"
    style="grid-area:footer; padding:20px 16px; text-align:center; color:var(--text-muted); font-size:11px; border-top:1px solid var(--border-default); margin:16px -20px 0 -20px; background:rgba(255,255,255,0.02); text-transform:uppercase; letter-spacing:2px; font-weight:bold;">
    &copy; <?= date('Y') ?> PIXEL FORGE // SYSTEM_CORE_v1.0.4
</footer>
</div> <!-- .app-container -->

<script src="<?= BASE_URL ?>/assets/js/achievements.js"></script>
</body>

</html>