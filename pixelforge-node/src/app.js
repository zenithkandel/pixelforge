const express = require('express');
const helmet = require('helmet');
const cookieParser = require('cookie-parser');
const cors = require('cors');
const { doubleCsrf } = require('csrf-csrf');
const path = require('path');
const config = require('./config');
const { getPool } = require('./database');

function createApp() {
  const app = express();

  app.use(helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        styleSrc: ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com"],
        fontSrc: ["'self'", "https://fonts.gstatic.com"],
        scriptSrc: ["'self'", "'unsafe-inline'"],
        imgSrc: ["'self'", "data:", "blob:"],
        connectSrc: ["'self'"],
        mediaSrc: ["'self'"],
        objectSrc: ["'none'"],
        frameSrc: ["'none'"],
        upgradeInsecureRequests: []
      }
    },
    hsts: {
      maxAge: 31536000,
      includeSubDomains: true,
      preload: true
    },
    referrerPolicy: { policy: 'strict-origin-when-cross-origin' },
    frameguard: { action: 'deny' },
    noSniff: true,
    xssFilter: true
  }));

  app.use(cors({
    origin: true,
    credentials: true
  }));

  app.use(express.json({ limit: '1mb' }));
  app.use(express.urlencoded({ extended: true, limit: '1mb' }));
  app.use(cookieParser());

  app.use(express.static(path.join(__dirname, '../public')));

  const { generateToken, doubleCsrfProtection } = doubleCsrf({
    getSecret: () => config.csrf.secret,
    cookieName: 'x-csrf-token',
    cookieOptions: {
      httpOnly: false,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict'
    },
    size: 64,
    ignoredMethods: ['GET', 'HEAD', 'OPTIONS']
  });

  app.use((req, res, next) => {
    req.csrfToken = () => generateToken(req, res);
    next();
  });

  app.set('db', getPool());
  app.set('config', config);

  const authRoutes = require('./routes/auth');
  const gameRoutes = require('./routes/game');
  const gridRoutes = require('./routes/grid');
  const userRoutes = require('./routes/user');
  const leaderboardRoutes = require('./routes/leaderboard');
  const adminRoutes = require('./routes/admin');

  app.use('/api/auth', authRoutes);
  app.use('/api/game', gameRoutes);
  app.use('/api/grid', gridRoutes);
  app.use('/api/user', userRoutes);
  app.use('/api/leaderboard', leaderboardRoutes);
  app.use('/api/admin', adminRoutes);

  app.use((err, req, res, next) => {
    const logger = require('./utils/logger');
    logger.error(err.stack);
    
    if (err.isOperational) {
      return res.status(err.statusCode).json({ ok: false, error: err.errorCode });
    }
    
    res.status(500).json({ ok: false, error: 'internal_error' });
  });

  return app;
}

module.exports = { createApp, doubleCsrfProtection };