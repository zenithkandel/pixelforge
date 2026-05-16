const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.static(path.join(__dirname, '../public')));

app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/index.html'));
});

app.get('/game', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/game.html'));
});

app.post('/api/game/score', express.json(), (req, res) => {
    const { score, player } = req.body;
    if (!score || score < 0) {
        return res.json({ ok: false, error: 'Invalid score' });
    }
    const scores = getHighScores();
    scores.push({ player: player || 'Anonymous', score, date: Date.now() });
    scores.sort((a, b) => b.score - a.score);
    const top10 = scores.slice(0, 10);
    saveHighScores(top10);
    res.json({ ok: true, rank: top10.findIndex(s => s.score === score && s.date === Date.now()) + 1, highScores: top10 });
});

app.get('/api/game/leaderboard', (req, res) => {
    res.json({ ok: true, scores: getHighScores() });
});

function getHighScores() {
    try {
        const data = require('fs').readFileSync(path.join(__dirname, '../scores.json'), 'utf8');
        return JSON.parse(data);
    } catch {
        return [];
    }
}

function saveHighScores(scores) {
    require('fs').writeFileSync(path.join(__dirname, '../scores.json'), JSON.stringify(scores, null, 2));
}

app.listen(PORT, () => {
    console.log(`Game server running at http://localhost:${PORT}`);
    console.log(`Press Space or Enter to start!`);
});