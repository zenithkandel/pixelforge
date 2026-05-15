const jwt = require('jsonwebtoken');
const config = require('../config');
const { error } = require('../utils/response');

function authRequired(req, res, next) {
  const authHeader = req.headers.authorization;
  
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return error(res, 'unauthorized', 401);
  }
  
  const token = authHeader.split(' ')[1];
  
  if (config.jwt.blacklist.has(token)) {
    return error(res, 'token_revoked', 401);
  }
  
  try {
    const payload = jwt.verify(token, config.jwt.secret);
    req.user = {
      userId: payload.userId,
      username: payload.username,
      isAdmin: payload.isAdmin || false
    };
    next();
  } catch (err) {
    if (err.name === 'TokenExpiredError') {
      return error(res, 'token_expired', 401);
    }
    return error(res, 'invalid_token', 401);
  }
}

function authOptional(req, res, next) {
  const authHeader = req.headers.authorization;
  
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    req.user = null;
    return next();
  }
  
  const token = authHeader.split(' ')[1];
  
  if (config.jwt.blacklist.has(token)) {
    req.user = null;
    return next();
  }
  
  try {
    const payload = jwt.verify(token, config.jwt.secret);
    req.user = {
      userId: payload.userId,
      username: payload.username,
      isAdmin: payload.isAdmin || false
    };
  } catch (err) {
    req.user = null;
  }
  
  next();
}

function adminRequired(req, res, next) {
  if (!req.user || !req.user.isAdmin) {
    return error(res, 'admin_required', 403);
  }
  next();
}

function generateTokens(user) {
  const accessToken = jwt.sign(
    {
      userId: user.id,
      username: user.username,
      isAdmin: user.is_admin === 1
    },
    config.jwt.secret,
    { expiresIn: config.jwt.accessExpiry }
  );
  
  const refreshToken = jwt.sign(
    {
      userId: user.id,
      username: user.username,
      type: 'refresh'
    },
    config.jwt.refreshSecret,
    { expiresIn: config.jwt.refreshExpiry }
  );
  
  return { accessToken, refreshToken };
}

function blacklistToken(token, expiresIn) {
  const expires = new Date(Date.now() + expiresIn);
  config.jwt.blacklist.add(token);
  
  setTimeout(() => {
    config.jwt.blacklist.delete(token);
  }, expiresIn);
}

module.exports = {
  authRequired,
  authOptional,
  adminRequired,
  generateTokens,
  blacklistToken
};