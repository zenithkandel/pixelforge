const express = require('express');
const bcrypt = require('bcrypt');
const crypto = require('crypto');
const jwt = require('jsonwebtoken');
const router = express.Router();
const { authRequired, generateTokens, blacklistToken } = require('../middleware/auth');
const { validateUsername, validateEmail, validatePassword } = require('../middleware/validate');
const { success, error, AppError } = require('../utils/response');
const emailService = require('../services/emailService');
const config = require('../config');

router.post('/register', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    let { username, email, password } = req.body;
    
    username = validateUsername(username);
    email = validateEmail(email);
    password = validatePassword(password);
    
    const [existingUser] = await pool.execute(
      'SELECT id FROM users WHERE username = ? OR email = ?',
      [username, email]
    );
    
    if (existingUser.length > 0) {
      throw new AppError('user_exists', 400, 'Username or email already taken');
    }
    
    const passwordHash = await bcrypt.hash(password, 12);
    const verificationToken = crypto.randomBytes(32).toString('hex');
    
    const [result] = await pool.execute(
      'INSERT INTO users (username, email, password_hash, verification_token, pxl_balance) VALUES (?, ?, ?, ?, 100)',
      [username, email, passwordHash, verificationToken]
    );
    
    const userId = result.insertId;
    
    await emailService.sendVerificationEmail(email, username, verificationToken);
    
    const { accessToken, refreshToken } = generateTokens({
      id: userId,
      username,
      is_admin: 0
    });
    
    res.cookie('refreshToken', refreshToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000
    });
    
    return success(res, {
      userId,
      username,
      accessToken,
      needsVerification: true
    }, 201);
  } catch (err) {
    next(err);
  }
});

router.post('/login', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const { username, password } = req.body;
    
    if (!username || !password) {
      throw new AppError('missing_credentials', 400, 'Username and password are required');
    }
    
    const [users] = await pool.execute(
      'SELECT * FROM users WHERE username = ? OR email = ?',
      [username.toLowerCase(), username.toLowerCase()]
    );
    
    if (users.length === 0) {
      throw new AppError('invalid_credentials', 401, 'Invalid username or password');
    }
    
    const user = users[0];
    
    if (!user.is_verified) {
      throw new AppError('email_not_verified', 403, 'Please verify your email first');
    }
    
    const passwordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!passwordValid) {
      throw new AppError('invalid_credentials', 401, 'Invalid username or password');
    }
    
    const sessionToken = crypto.randomBytes(32).toString('hex');
    
    await pool.execute(
      'UPDATE users SET last_login = NOW(), active_session = ? WHERE id = ?',
      [sessionToken, user.id]
    );
    
    const { accessToken, refreshToken } = generateTokens(user);
    
    res.cookie('refreshToken', refreshToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000
    });
    
    return success(res, {
      userId: user.id,
      username: user.username,
      pxlBalance: user.pxl_balance,
      isAdmin: user.is_admin === 1,
      accessToken
    });
  } catch (err) {
    next(err);
  }
});

router.post('/refresh', async (req, res, next) => {
  try {
    const refreshToken = req.cookies.refreshToken;
    
    if (!refreshToken) {
      throw new AppError('no_token', 401, 'Refresh token required');
    }
    
    let payload;
    try {
      payload = jwt.verify(refreshToken, config.jwt.refreshSecret);
    } catch (err) {
      throw new AppError('invalid_token', 401, 'Invalid refresh token');
    }
    
    const pool = req.app.get('db');
    const [users] = await pool.execute(
      'SELECT * FROM users WHERE id = ?',
      [payload.userId]
    );
    
    if (users.length === 0 || !users[0].is_verified) {
      throw new AppError('user_not_found', 401, 'User not found or not verified');
    }
    
    const user = users[0];
    const tokens = generateTokens(user);
    
    res.cookie('refreshToken', tokens.refreshToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000
    });
    
    return success(res, { accessToken: tokens.accessToken });
  } catch (err) {
    next(err);
  }
});

router.post('/logout', authRequired, async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader.split(' ')[1];
    
    blacklistToken(token, 15 * 60 * 1000);
    
    res.clearCookie('refreshToken');
    
    return success(res, { message: 'Logged out successfully' });
  } catch (err) {
    next(err);
  }
});

router.get('/verify-email', async (req, res, next) => {
  try {
    const { token } = req.query;
    
    if (!token) {
      throw new AppError('missing_token', 400, 'Verification token required');
    }
    
    const pool = req.app.get('db');
    const [users] = await pool.execute(
      'SELECT * FROM users WHERE verification_token = ?',
      [token]
    );
    
    if (users.length === 0) {
      throw new AppError('invalid_token', 400, 'Invalid verification token');
    }
    
    const user = users[0];
    
    if (user.is_verified) {
      return success(res, { message: 'Email already verified' });
    }
    
    await pool.execute(
      'UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?',
      [user.id]
    );
    
    return success(res, { message: 'Email verified successfully' });
  } catch (err) {
    next(err);
  }
});

router.post('/forgot-password', async (req, res, next) => {
  try {
    const { email } = req.body;
    
    if (!email) {
      throw new AppError('missing_email', 400, 'Email is required');
    }
    
    const pool = req.app.get('db');
    const [users] = await pool.execute(
      'SELECT * FROM users WHERE email = ?',
      [email.toLowerCase()]
    );
    
    if (users.length > 0) {
      const user = users[0];
      const resetToken = crypto.randomBytes(32).toString('hex');
      const expires = new Date(Date.now() + 60 * 60 * 1000);
      
      await pool.execute(
        'UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?',
        [resetToken, expires, user.id]
      );
      
      await emailService.sendPasswordResetEmail(email, user.username, resetToken);
    }
    
    return success(res, { message: 'If an account exists, a reset email has been sent' });
  } catch (err) {
    next(err);
  }
});

router.post('/reset-password', async (req, res, next) => {
  try {
    const { token, password } = req.body;
    
    if (!token || !password) {
      throw new AppError('missing_fields', 400, 'Token and new password required');
    }
    
    validatePassword(password);
    
    const pool = req.app.get('db');
    const [users] = await pool.execute(
      'SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()',
      [token]
    );
    
    if (users.length === 0) {
      throw new AppError('invalid_token', 400, 'Invalid or expired reset token');
    }
    
    const user = users[0];
    const passwordHash = await bcrypt.hash(password, 12);
    
    await pool.execute(
      'UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?',
      [passwordHash, user.id]
    );
    
    return success(res, { message: 'Password reset successfully' });
  } catch (err) {
    next(err);
  }
});

module.exports = router;