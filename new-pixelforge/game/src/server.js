const express = require('express');
const path = require('path');
const fs = require('fs');

const app = express();
const PORT = process.env.PORT || 3000;
const SCORES_FILE = path.join(__dirname, '../scores.json');

app.use(express.static(path.join(__dirname, '../public')));
app.use(express.json());

app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/index.html'));
});

app.get('/game', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/game.html'));
});

app.post('/api/scores', (req, res) => {
    const { score, player, wave, time } = req.body;
    if (!score || score < 0) {
        return res.json({ ok: false, error: 'Invalid score' });
    }
    const scores = getScores();
    scores.push({ 
        player: player || 'Anonymous', 
        score: Math.floor(score), 
        wave: wave || 1,
        time: time || 0,
        date: Date.now() 
    });
    scores.sort((a, b) => b.score - a.score);
    const top20 = scores.slice(0, 20);
    saveScores(top20);
    const rank = top20.findIndex(s => s.score === Math.floor(score) && s.date === Date.now()) + 1;
    res.json({ ok: true, rank, highScores: top20 });
});

app.get('/api/scores', (req, res) => {
    res.json({ ok: true, scores: getScores() });
});

function getScores() {
    try {
        const data = fs.readFileSync(SCORES_FILE, 'utf8');
        return JSON.parse(data);
    } catch {
        return [];
    }
}

function saveScores(scores) {
    fs.writeFileSync(SCORES_FILE, JSON.stringify(scores, null, 2));
}

app.listen(PORT, () => {
    console.log(`🚀 VOID BREAKER - Space Arcade Shooter`);
    console.log(`   Server: http://localhost:${PORT}`);
    console.log(`   Controls: Arrow Keys/WASD to move, SPACE to shoot`);
});