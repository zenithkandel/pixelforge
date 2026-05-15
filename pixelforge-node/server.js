const fs = require('fs');
const path = require('path');

if (!fs.existsSync('.env')) {
  const envExample = fs.readFileSync('.env.example', 'utf8');
  fs.writeFileSync('.env', envExample);
  console.log('\n========================================');
  console.log('.env file created. Please configure your SMTP settings.');
  console.log('Edit .env and run "node server.js" again.\n');
  console.log('Required: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS');
  console.log('Optional: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME');
  console.log('========================================\n');
  process.exit(0);
}

require('dotenv').config();
const config = require('./src/config');
const secrets = config.initSecrets();

if (secrets) {
  console.log('Generated application secrets.');
}

const { createPool, testConnection, closePool } = require('./src/database');
const { runMigration } = require('./src/migrations/001_initial');
const { createApp } = require('./src/app');
const { initScheduling, stopAll } = require('./src/services/scheduling');
const logger = require('./src/utils/logger');

async function startServer() {
  try {
    console.log('PixelForge Node.js Edition v2.0');
    console.log('================================\n');

    createPool();
    
    console.log('Testing database connection...');
    const connected = await testConnection();
    
    if (!connected) {
      console.error('Failed to connect to database. Please check your MySQL settings in .env');
      process.exit(1);
    }
    
    console.log('Database connection successful.\n');

    console.log('Running database migrations...');
    const pool = createPool();
    await runMigration(pool);

    console.log('Creating Express application...');
    const app = createApp();

    const PORT = config.server.port;
    const HOST = process.env.HOST || '0.0.0.0';

    const server = app.listen(PORT, HOST, () => {
      console.log(`\nPixelForge server running on http://${HOST === '0.0.0.0' ? 'localhost' : HOST}:${PORT}`);
      console.log('\n================================');
      console.log('Server ready!');
      console.log('================================\n');
    });

    initScheduling(pool);

    const shutdown = async (signal) => {
      console.log(`\n${signal} received. Shutting down gracefully...`);
      
      stopAll();
      
      server.close(() => {
        console.log('HTTP server closed.');
      });

      await closePool();
      console.log('Database connections closed.');
      
      process.exit(0);
    };

    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGINT', () => shutdown('SIGINT'));

  } catch (err) {
    logger.error('Failed to start server:', err);
    console.error('Failed to start server:', err);
    process.exit(1);
  }
}

startServer();