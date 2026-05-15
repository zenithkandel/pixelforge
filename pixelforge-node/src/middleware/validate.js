const { AppError } = require('../utils/response');

const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const colorRegex = /^#[0-9A-Fa-f]{6}$/;

function validateUsername(username) {
  if (!username || typeof username !== 'string') {
    throw new AppError('invalid_username', 400, 'Username is required');
  }
  if (!usernameRegex.test(username)) {
    throw new AppError('invalid_username', 400, 'Username must be 3-20 characters, alphanumeric and underscores only');
  }
  return username.toLowerCase();
}

function validateEmail(email) {
  if (!email || typeof email !== 'string') {
    throw new AppError('invalid_email', 400, 'Email is required');
  }
  if (!emailRegex.test(email)) {
    throw new AppError('invalid_email', 400, 'Invalid email format');
  }
  return email.toLowerCase();
}

function validatePassword(password) {
  if (!password || typeof password !== 'string') {
    throw new AppError('invalid_password', 400, 'Password is required');
  }
  if (password.length < 8) {
    throw new AppError('invalid_password', 400, 'Password must be at least 8 characters');
  }
  return password;
}

function validateColor(color) {
  if (!color || typeof color !== 'string') {
    throw new AppError('invalid_color', 400, 'Color is required');
  }
  if (!colorRegex.test(color)) {
    throw new AppError('invalid_color', 400, 'Color must be a valid hex color (e.g., #FF0000)');
  }
  return color.toUpperCase();
}

function validateCoordinate(value, name) {
  const num = parseInt(value, 10);
  if (isNaN(num) || num < 0 || num >= 800) {
    throw new AppError('invalid_coordinate', 400, `${name} must be between 0 and 799`);
  }
  return num;
}

function validatePositiveInt(value, name) {
  const num = parseInt(value, 10);
  if (isNaN(num) || num < 0) {
    throw new AppError('invalid_value', 400, `${name} must be a positive integer`);
  }
  return num;
}

function validateScore(score) {
  const num = parseInt(score, 10);
  if (isNaN(num) || num < 0 || num > 1000000) {
    throw new AppError('invalid_score', 400, 'Invalid score value');
  }
  return num;
}

function validateSessionToken(token) {
  if (!token || typeof token !== 'string' || token.length !== 64) {
    throw new AppError('invalid_session', 400, 'Invalid session token');
  }
  return token;
}

module.exports = {
  validateUsername,
  validateEmail,
  validatePassword,
  validateColor,
  validateCoordinate,
  validatePositiveInt,
  validateScore,
  validateSessionToken
};