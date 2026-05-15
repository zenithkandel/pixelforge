const { doubleCsrf } = require('csrf-csrf');
const config = require('../config');

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

function csrfMiddleware(req, res, next) {
  req.csrfToken = () => generateToken(req, res);
  doubleCsrfProtection(req, res, next);
}

function csrfIgnore(req, res, next) {
  req.csrfToken = () => '';
  next();
}

module.exports = {
  csrfMiddleware,
  csrfIgnore,
  doubleCsrfProtection
};