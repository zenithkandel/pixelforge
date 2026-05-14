<?php require_once dirname(__DIR__) . '/includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; }
        th { background: #1a1a1a; color: #00F5FF; }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/public/includes/sidebar.php'; ?>
    <main class="content">
        <h2>Leaderboard (Daily)</h2>
        <table>
            <thead><tr><th>Rank</th><th>Player</th><th>Score</th><th>PXL Earned</th></tr></thead>
            <tbody id="lb-body"></tbody>
        </table>
    </main>
    <script type="module">
        import { apiGet } from '/assets/js/api.js';
        apiGet('/api/leaderboard.php?type=daily').then(res => {
            if (res.ok) {
                const tbody = document.getElementById('lb-body');
                res.data.forEach((row, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${idx + 1}</td>
                        <td><a href="/profile.php?username=${row.username}" style="color:#fff;">${row.username}</a></td>
                        <td>${row.score}</td>
                        <td>${row.pxl_earned}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        });
    </script>
</body>
</html>
