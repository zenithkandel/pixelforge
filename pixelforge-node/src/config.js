require('dotenv').config();
const crypto = require('crypto');

const config = {
  db: {
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'pixelforge',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'pixelforge',
    waitForConnections: true,
    connectionLimit: 20,
    multipleStatements: false,
    namedPlaceholders: true
  },
  smtp: {
    host: process.env.SMTP_HOST || 'smtp.example.com',
    port: parseInt(process.env.SMTP_PORT) || 587,
    secure: process.env.SMTP_SECURE === 'true' || process.env.SMTP_PORT === '465',
    user: process.env.SMTP_USER || '',
    pass: process.env.SMTP_PASS || '',
    from: process.env.SMTP_FROM || 'noreply@example.com',
    fromName: process.env.SMTP_FROM_NAME || 'PixelForge'
  },
  jwt: {
    secret: process.env.JWT_SECRET,
    refreshSecret: process.env.REFRESH_TOKEN_SECRET,
    accessExpiry: '15m',
    refreshExpiry: '7d',
    blacklist: new Set()
  },
  csrf: {
    secret: process.env.CSRF_SECRET
  },
  hmac: {
    secret: process.env.GAME_HMAC_SECRET
  },
  server: {
    port: parseInt(process.env.PORT) || 3000
  },
  grid: {
    width: 800,
    height: 800,
    chunkSize: 64,
    pixelCost: 1,
    gemBonus: 3,
    canvasBoostDuration: 24 * 60 * 60 * 1000,
    cacheMaxSize: 200,
    cacheTTL: 30000
  },
  game: {
    baseSpeed: 3,
    speedIncrement: 0.5,
    maxSpeed: 12,
    baseScore: 1,
    comboMultiplierMax: 5,
    checkpointInterval: 500
  },
  rateLimit: {
    windowMs: 60000,
    maxRequests: {
      default: 100,
      auth: 10,
      pixel: 10,
      game: 5
    }
  }
};

function generateSecrets() {
  const secrets = {
    JWT_SECRET: crypto.randomBytes(64).toString('hex'),
    REFRESH_TOKEN_SECRET: crypto.randomBytes(64).toString('hex'),
    CSRF_SECRET: crypto.randomBytes(32).toString('hex'),
    GAME_HMAC_SECRET: crypto.randomBytes(32).toString('hex')
  };

  let envContent = require('fs').readFileSync('.env', 'utf8');
  
  for (const [key, value] of Object.entries(secrets)) {
    const regex = new RegExp(`^${key}=.*$`, 'm');
    if (regex.test(envContent)) {
      envContent = envContent.replace(regex, `${key}=${value}`);
    } else {
      envContent += `\n${key}=${value}`;
    }
  }
  
  require('fs').writeFileSync('.env', envContent);
  
  process.env.JWT_SECRET = secrets.JWT_SECRET;
  process.env.REFRESH_TOKEN_SECRET = secrets.REFRESH_TOKEN_SECRET;
  process.env.CSRF_SECRET = secrets.CSRF_SECRET;
  process.env.GAME_HMAC_SECRET = secrets.GAME_HMAC_SECRET;
  
  config.jwt.secret = secrets.JWT_SECRET;
  config.jwt.refreshSecret = secrets.REFRESH_TOKEN_SECRET;
  config.csrf.secret = secrets.CSRF_SECRET;
  config.hmac.secret = secrets.GAME_HMAC_SECRET;

  return secrets;
}

function initSecrets() {
  let needsGeneration = false;
  
  if (!process.env.JWT_SECRET) needsGeneration = true;
  if (!process.env.REFRESH_TOKEN_SECRET) needsGeneration = true;
  if (!process.env.CSRF_SECRET) needsGeneration = true;
  if (!process.env.GAME_HMAC_SECRET) needsGeneration = true;

  if (needsGeneration) {
    return generateSecrets();
  }

  config.jwt.secret = process.env.JWT_SECRET;
  config.jwt.refreshSecret = process.env.REFRESH_TOKEN_SECRET;
  config.csrf.secret = process.env.CSRF_SECRET;
  config.hmac.secret = process.env.GAME_HMAC_SECRET;

  return null;
}

module.exports = config;
module.exports.initSecrets = initSecrets;
module.exports.generateSecrets = generateSecrets;