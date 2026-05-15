function success(res, data, statusCode = 200) {
  return res.status(statusCode).json({ ok: true, data });
}

function error(res, message, statusCode = 400, errorCode = null) {
  return res.status(statusCode).json({
    ok: false,
    error: errorCode || message
  });
}

function paginated(res, data, pagination) {
  return res.status(200).json({
    ok: true,
    data,
    pagination
  });
}

function escapeHtml(str) {
  if (typeof str !== 'string') return str;
  const htmlEscapes = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#x27;',
    '/': '&#x2F;'
  };
  return str.replace(/[&<>"'\/]/g, char => htmlEscapes[char]);
}

function sanitizeUsername(username) {
  return username.replace(/[^a-zA-Z0-9_]/g, '').substring(0, 20);
}

function sanitizeColor(color) {
  if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
    return color.toUpperCase();
  }
  return '#FFFFFF';
}

function validateCoord(x, y) {
  const max = 800;
  return x >= 0 && x < max && y >= 0 && y < max;
}

class AppError extends Error {
  constructor(message, statusCode = 400, errorCode = null) {
    super(message);
    this.statusCode = statusCode;
    this.errorCode = errorCode || message;
    this.isOperational = true;
  }
}

module.exports = {
  success,
  error,
  paginated,
  escapeHtml,
  sanitizeUsername,
  sanitizeColor,
  validateCoord,
  AppError
};