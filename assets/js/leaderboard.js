(function() {
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var BASE_URL = window.BASE_URL || '';
            window.location.href = BASE_URL + '/leaderboard.php?period=' + this.dataset.lb;
        });
    });
})();