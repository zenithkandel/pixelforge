</main>

<aside class="app-widgets">
    <?php if (isset($nav_user)): ?>
        <div class="widget">
            <div class="widget-title">Player Profile</div>
            <div class="profile-widget">
                <div class="stat-grid">
                    <div class="stat-box">
                        <span class="label">Best Score</span>
                        <span class="value"><?= number_format($nav_user['best_score'] ?? 0) ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="label">Level</span>
                        <span class="value"><?= (int) $nav_user['level'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="widget">
            <div class="widget-title">Achievements</div>
            <div id="achievements-mini-list" style="display:flex; flex-direction:column; gap:10px;">
                <!-- To be populated by JS -->
                <div style="font-size:12px; color:var(--text-muted); text-align:center;">Loading...</div>
            </div>
        </div>
    <?php endif; ?>
</aside>

<footer class="app-footer"
    style="grid-area:footer; padding:16px; text-align:center; color:var(--text-muted); font-size:12px; border-top:1px solid var(--border-dim); margin:16px -16px 0 -16px;">
    &copy; <?= date('Y') ?> PIXEL FLAP — Built for the community.
</footer>
</div> <!-- .app-container -->

<script src="<?= BASE_URL ?>/assets/js/achievements.js"></script>
</body>

</html>