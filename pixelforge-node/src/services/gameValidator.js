const config = require('../config');

function validateScore(data) {
  const {
    score,
    duration,
    startSeed,
    obstaclesHit,
    powerUpsCollected,
    checkpointsCount
  } = data;

  if (score < 0 || score > 1000000) {
    return { valid: false, reason: 'Score out of valid range' };
  }

  if (duration < 1000 || duration > 300000) {
    return { valid: false, reason: 'Invalid game duration' };
  }

  const expectedMaxScore = Math.floor(duration / 10) * config.game.baseScore * config.game.comboMultiplierMax;

  if (score > expectedMaxScore * 1.5) {
    return { valid: false, reason: 'Score exceeds theoretical maximum for game duration' };
  }

  const minScoreForDuration = Math.floor(duration / 1000) * 0.5;

  if (score < minScoreForDuration) {
    return { valid: false, reason: 'Score too low for game duration' };
  }

  if (obstaclesHit !== undefined && obstaclesHit > Math.floor(duration / 1000) * 3) {
    return { valid: false, reason: 'Suspicious number of obstacles hit' };
  }

  if (checkpointsCount !== undefined) {
    const maxCheckpoints = Math.floor(duration / config.game.checkpointInterval);
    if (checkpointsCount > maxCheckpoints) {
      return { valid: false, reason: 'Too many checkpoints for game duration' };
    }
  }

  return { valid: true };
}

function verifyHmac(data, hmac, secret) {
  const crypto = require('crypto');
  const expectedHmac = crypto
    .createHmac('sha256', secret)
    .update(data)
    .digest('hex');
  
  return hmac === expectedHmac;
}

function generateGameKey(sessionToken) {
  const crypto = require('crypto');
  return crypto
    .createHmac('sha256', config.hmac.secret)
    .update(sessionToken)
    .digest('hex');
}

module.exports = {
  validateScore,
  verifyHmac,
  generateGameKey
};