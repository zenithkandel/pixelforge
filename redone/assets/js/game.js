// Minimal placeholder game script
let score = 0;
function startGame() {
    score = Math.floor(Math.random() * 50);
    gameOver();
}
function gameOver() {
    const csrf = document.getElementById('csrf-token').value;
    fetch(APP_URL + '/api/save_score.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'score=' + score + '&csrf_token=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(data => {
        alert('Score saved. Earned: ' + data.currency_earned + ' New bal: ' + data.new_balance);
    });
}
window.onload = () => {
    document.getElementById('game-root').innerHTML = '<button id="play">Play (random)</button>';
    document.getElementById('play').addEventListener('click', startGame);
};