<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$username = $_GET['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include dirname(__DIR__) . '/public/includes/sidebar.php'; ?>
    <main class="content">
        <h2 id="p-username">Loading Profile...</h2>
        <div id="p-stats"></div>
    </main>
    <script type="module">
        import { apiGet } from '/assets/js/api.js';
        const username = new URLSearchParams(window.location.search).get('username');
        if (username) {
            apiGet('/api/user/profile.php?username=' + encodeURIComponent(username)).then(res => {
                if (res.ok) {
                    document.getElementById('p-username').innerText = res.data.username;
                    document.getElementById('p-stats').innerHTML = `
                        <p>Total PXL Earned: ${res.data.total_pxl_earned}</p>
                        <p>Pixels Painted: ${res.data.pixels_painted}</p>
                        <p>Best Score: ${res.data.best_score}</p>
                    `;
                } else {
                    document.getElementById('p-username').innerText = "User not found.";
                }
            });
        }
    </script>
</body>
</html>
