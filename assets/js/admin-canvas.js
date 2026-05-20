(function() {
    var canvas = document.getElementById('admin-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var pixels = [];
    var cellSize = 8;
    var BASE_URL = window.AppConfig && window.AppConfig.baseUrl ? window.AppConfig.baseUrl : '';
    var CSRF_TOKEN = window.AppConfig && window.AppConfig.csrfToken ? window.AppConfig.csrfToken : '';

    function render() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 800, 800);
        var pixelMap = {};
        pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = 0; row < 100; row++) {
            for (var col = 0; col < 100; col++) {
                var p = pixelMap[col + ',' + row];
                ctx.fillStyle = p ? p.color : '#1a1a1a';
                ctx.fillRect(col * cellSize, row * cellSize, cellSize + 0.5, cellSize + 0.5);
            }
        }
    }

    canvas.addEventListener('click', function(e) {
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);
        var col = Math.floor(mx / cellSize);
        var row = Math.floor(my / cellSize);
        if (col < 0 || col >= 100 || row < 0 || row >= 100) return;

        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input name="csrf_token" value="' + CSRF_TOKEN + '"><input name="action" value="erase_pixel"><input name="x" value="' + col + '"><input name="y" value="' + row + '">';
        document.body.appendChild(form);
        form.submit();
    });

    fetch(BASE_URL + '/api/get_canvas.php')
        .then(function(res) { return res.json(); })
        .then(function(data) { pixels = data.pixels; render(); })
        .catch(function() {});
})();